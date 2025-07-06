<?php

namespace HCart\LaravelMultiCart\Services;

use HCart\LaravelMultiCart\Attributes\AttributeReader;
use HCart\LaravelMultiCart\Contracts\CartConfigInterface;
use HCart\LaravelMultiCart\Contracts\CartInterface;
use HCart\LaravelMultiCart\Contracts\ShippableInterface;
use HCart\LaravelMultiCart\Contracts\TaxableInterface;
use HCart\LaravelMultiCart\Events\CartCreated;
use HCart\LaravelMultiCart\Events\CartUpdated;
use HCart\LaravelMultiCart\Events\ItemAdded;
use HCart\LaravelMultiCart\Events\ItemRemoved;
use HCart\LaravelMultiCart\Events\ItemUpdated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CartService implements CartInterface
{
    protected CartManager $manager;

    protected CartConfigInterface $config;

    protected string $name;

    protected string $provider;

    protected ?Model $user = null;

    protected ?string $sessionId = null;

    protected array $cartData = [];

    protected bool $loaded = false;

    public function __construct(CartManager $manager, CartConfigInterface $config, string $name, string $provider)
    {
        $this->manager = $manager;
        $this->config = $config;
        $this->name = $name;
        $this->provider = $provider;
        $this->sessionId = session()->getId();
    }

    /**
     * Add item to cart
     */
    public function add(Model $cartable, int $quantity = 1, array $attributes = []): self
    {
        return \DB::transaction(function () use ($cartable, $quantity, $attributes) {
            $this->loadCart();

            $itemData = $this->prepareItemData($cartable, $quantity, $attributes);
            $item = $this->processCartItem($itemData);

            $this->save();

            // For database provider, sync in transaction
            if ($this->provider === 'database') {
                $this->syncCartItemsToDatabase();
            }

            event(new ItemAdded($this->name, $cartable, $quantity, $attributes, $item));

            return $this;
        });
    }

    /**
     * Prepare item data for processing
     */
    private function prepareItemData(Model $cartable, int $quantity, array $attributes): array
    {
        $price = $cartable->getCartPrice();

        // Extract pricing settings from attributes
        $discountSettings = $attributes['discount_settings'] ?? null;
        $taxSettings = $this->resolveTaxSettings($cartable, $attributes);
        $shippingSettings = $this->resolveShippingSettings($cartable, $attributes);

        // Remove pricing settings from attributes to avoid duplication
        $cleanAttributes = array_diff_key($attributes, array_flip(['discount_settings', 'tax_settings', 'shipping_settings']));

        // Generate unique key using cleaned attributes to ensure consistency
        $uniqueKey = $this->generateUniqueKey($cartable, $cleanAttributes);

        return [
            'cartable' => $cartable,
            'quantity' => $quantity,
            'price' => $price,
            'attributes' => $cleanAttributes,
            'unique_key' => $uniqueKey,
            'discount_settings' => $discountSettings,
            'tax_settings' => $taxSettings,
            'shipping_settings' => $shippingSettings,
            'original_attributes' => $attributes,
        ];
    }

    /**
     * Add multiple items to cart in a single operation
     */
    public function addBulk(array $items): self
    {
        if (empty($items)) {
            return $this;
        }

        return \DB::transaction(function () use ($items) {
            $this->loadCart();

            $addedItems = [];
            $processedItems = $this->preprocessBulkItems($items);

            // Process all items efficiently
            foreach ($processedItems as $itemData) {
                $item = $this->processCartItem($itemData);
                $addedItems[] = [
                    'cartable' => $itemData['cartable'],
                    'quantity' => $itemData['quantity'],
                    'attributes' => $itemData['original_attributes'],
                    'item' => $item,
                ];
            }

            $this->save();

            // For database provider, sync in a single transaction
            if ($this->provider === 'database') {
                $this->syncCartItemsToDatabase();
            }

            // Fire events for all added items
            $this->dispatchBulkItemEvents($addedItems);

            return $this;
        });
    }

    /**
     * Preprocess bulk items for validation and attribute cleaning
     */
    private function preprocessBulkItems(array $items): array
    {
        $processedItems = [];

        foreach ($items as $itemData) {
            $this->validateBulkItemData($itemData);

            $cartable = $itemData['cartable'];
            $quantity = $itemData['quantity'] ?? 1;
            $attributes = $itemData['attributes'] ?? [];
            $price = $itemData['price'] ?? $cartable->getCartPrice();

            // Extract and resolve pricing settings from attributes
            $discountSettings = $attributes['discount_settings'] ?? null;
            $taxSettings = $this->resolveTaxSettings($cartable, $attributes);
            $shippingSettings = $this->resolveShippingSettings($cartable, $attributes);

            // Remove pricing settings from attributes to avoid duplication
            $cleanAttributes = array_diff_key($attributes, array_flip(['discount_settings', 'tax_settings', 'shipping_settings']));

            // Generate unique key using cleaned attributes to ensure consistency
            $uniqueKey = $this->generateUniqueKey($cartable, $cleanAttributes);

            $processedItems[] = [
                'cartable' => $cartable,
                'quantity' => $quantity,
                'price' => $price,
                'attributes' => $cleanAttributes,
                'unique_key' => $uniqueKey,
                'discount_settings' => $discountSettings,
                'tax_settings' => $taxSettings,
                'shipping_settings' => $shippingSettings,
                'original_attributes' => $attributes,
            ];
        }

        return $processedItems;
    }

    /**
     * Validate bulk item data
     */
    private function validateBulkItemData(array $itemData): void
    {
        if (! isset($itemData['cartable'])) {
            throw new \InvalidArgumentException('Each item must have a cartable property');
        }

        if (! $itemData['cartable'] instanceof Model) {
            throw new \InvalidArgumentException('Cartable must be an instance of Illuminate\Database\Eloquent\Model');
        }
    }

    /**
     * Process a single cart item (add or update existing)
     */
    private function processCartItem(array $itemData): array
    {
        $uniqueKey = $itemData['unique_key'];
        $quantity = $itemData['quantity'];

        // Check for existing item
        $existingIndex = $this->findExistingItemIndex($uniqueKey);

        if ($existingIndex !== null && $this->config->shouldPreventDuplicates()) {
            // Update existing item quantity
            $this->cartData['items'][$existingIndex]['quantity'] += $quantity;

            return $this->cartData['items'][$existingIndex];
        } else {
            // Add new item
            $item = $this->createCartItemData($itemData);
            $this->cartData['items'][] = $item;

            return $item;
        }
    }

    /**
     * Find existing item index by unique key
     */
    private function findExistingItemIndex(string $uniqueKey): ?int
    {
        foreach ($this->cartData['items'] as $index => $item) {
            $existingUniqueKey = $item['unique_key'] ?? $this->generateUniqueKeyFromItem($item);
            if ($existingUniqueKey === $uniqueKey) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Create cart item data array
     */
    private function createCartItemData(array $itemData): array
    {
        return [
            'id' => uniqid(),
            'cartable_id' => $itemData['cartable']->getKey(),
            'cartable_type' => get_class($itemData['cartable']),
            'quantity' => $itemData['quantity'],
            'price' => $itemData['price'],
            'attributes' => $itemData['attributes'],
            'unique_key' => $itemData['unique_key'],
            'discount_settings' => $itemData['discount_settings'],
            'tax_settings' => $itemData['tax_settings'],
            'shipping_settings' => $itemData['shipping_settings'],
            'pieces_per_shipping' => $itemData['shipping_settings']['pieces_per_shipping'] ?? 1,
            'max_shipping_charges' => $itemData['shipping_settings']['max_shipping_charges'] ?? null,
        ];
    }

    /**
     * Dispatch events for bulk added items
     */
    private function dispatchBulkItemEvents(array $addedItems): void
    {
        foreach ($addedItems as $addedItem) {
            event(new ItemAdded(
                $this->name,
                $addedItem['cartable'],
                $addedItem['quantity'],
                $addedItem['attributes'],
                $addedItem['item']
            ));
        }
    }

    /**
     * Update cart item
     */
    public function update(string|int $itemId, array $data): self
    {
        $this->loadCart();

        foreach ($this->cartData['items'] as $index => $item) {
            if ($item['id'] == $itemId) {
                $oldData = $item;

                if (isset($data['quantity'])) {
                    $this->cartData['items'][$index]['quantity'] = max(1, (int) $data['quantity']);
                }

                if (isset($data['price'])) {
                    $this->cartData['items'][$index]['price'] = (float) $data['price'];
                }

                if (isset($data['attributes'])) {
                    $this->cartData['items'][$index]['attributes'] = array_merge($item['attributes'], $data['attributes']);
                }

                $newData = $this->cartData['items'][$index];

                // Call update callback if provided
                $callback = $this->config->getItemUpdateCallback();
                if ($callback) {
                    $callback($newData, $oldData, $newData);
                }

                $this->save();

                // For database provider, also sync CartItem records
                if ($this->provider === 'database') {
                    $this->syncCartItemsToDatabase();
                }

                event(new ItemUpdated($this->name, $itemId, $oldData, $newData));

                return $this;
            }
        }

        throw new \HCart\LaravelMultiCart\Exceptions\CartItemNotFoundException($itemId);
    }

    /**
     * Remove item from cart
     */
    public function remove(string|int $itemId): bool
    {
        $this->loadCart();

        foreach ($this->cartData['items'] as $index => $item) {
            if ($item['id'] == $itemId) {
                // Call remove callback if provided
                $callback = $this->config->getItemRemoveCallback();
                if ($callback) {
                    $callback($item);
                }

                unset($this->cartData['items'][$index]);
                $this->cartData['items'] = array_values($this->cartData['items']); // Re-index

                $this->save();

                // For database provider, also sync CartItem records
                if ($this->provider === 'database') {
                    $this->syncCartItemsToDatabase();
                }

                event(new ItemRemoved($this->name, $itemId, $item));

                return true;
            }
        }

        throw new \HCart\LaravelMultiCart\Exceptions\CartItemNotFoundException($itemId);
    }

    /**
     * Clear all items from cart
     */
    public function clear(): bool
    {
        $this->loadCart();
        $this->cartData['items'] = [];
        $this->save();

        // For database provider, also sync CartItem records
        if ($this->provider === 'database') {
            $this->syncCartItemsToDatabase();
        }

        return true;
    }

    /**
     * Get total number of items
     */
    public function count(): int
    {
        $this->loadCart();

        return array_sum(array_column($this->cartData['items'], 'quantity'));
    }

    /**
     * Get total price including tax, discount, and shipping
     */
    public function total(): float
    {
        return $this->subtotal() - $this->totalDiscount() + $this->totalTax() + $this->totalShipping();
    }

    /**
     * Get subtotal (before tax)
     */
    public function subtotal(): float
    {
        $this->loadCart();
        $total = 0;

        foreach ($this->cartData['items'] as $item) {
            $total += $item['price'] * $item['quantity'];
        }

        return (float) $total;
    }

    /**
     * Get tax amount (backwards compatibility - use totalTax() for new code)
     */
    public function tax(): float
    {
        return $this->totalTax();
    }

    /**
     * Get all cart items
     */
    public function items(): Collection
    {
        $this->loadCart();

        return collect($this->cartData['items']);
    }

    /**
     * Get specific item
     */
    public function get(string|int $itemId): ?Model
    {
        $this->loadCart();

        foreach ($this->cartData['items'] as $item) {
            if ($item['id'] == $itemId) {
                $model = $item['cartable_type'];

                return $model::find($item['cartable_id']);
            }
        }

        return null;
    }

    /**
     * Check if item exists in cart
     */
    public function has(Model $cartable): bool
    {
        $this->loadCart();

        foreach ($this->cartData['items'] as $item) {
            if ($item['cartable_id'] == $cartable->getKey() && $item['cartable_type'] == get_class($cartable)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get quantity of specific item
     */
    public function quantity(Model $cartable): int
    {
        $this->loadCart();
        $total = 0;

        foreach ($this->cartData['items'] as $item) {
            if ($item['cartable_id'] == $cartable->getKey() && $item['cartable_type'] == get_class($cartable)) {
                $total += $item['quantity'];
            }
        }

        return $total;
    }

    /**
     * Set cart for specific user
     */
    public function forUser(Model $user): self
    {
        $this->user = $user;

        // If cart is already loaded, update the user info and save
        if ($this->loaded) {
            $this->cartData['user_id'] = $user->getKey();
            $this->cartData['user_type'] = get_class($user);
            $this->save();
        }

        return $this;
    }

    /**
     * Set cart for specific session
     */
    public function forSession(string $sessionId): self
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    /**
     * Get the user associated with this cart
     */
    public function getUser(): ?Model
    {
        return $this->user;
    }

    /**
     * Get the cart ID (for database provider)
     */
    public function getCartId(): ?int
    {
        if ($this->provider !== 'database') {
            return null;
        }

        $this->loadCart();

        // If cart data doesn't have ID, try to get it from database
        if (! isset($this->cartData['id'])) {
            $cartModel = $this->config->getCartModel();
            $cart = $cartModel::where('name', $this->name)->first();

            return $cart ? $cart->id : null;
        }

        return $this->cartData['id'];
    }

    /**
     * Set cart configuration
     */
    public function setConfig(array $config): self
    {
        $this->loadCart();
        $this->cartData['config'] = array_merge($this->cartData['config'] ?? [], $config);
        $this->save();

        return $this;
    }

    /**
     * Set cart configuration using a config object
     */
    public function withConfig(CartConfigInterface $config): self
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Get cart configuration object
     */
    public function getCartConfig(): CartConfigInterface
    {
        return $this->config;
    }

    /**
     * Get cart configuration
     */
    public function getConfig(): array
    {
        $this->loadCart();

        return $this->cartData['config'] ?? [];
    }

    /**
     * Clone the cart to a new cart name
     */
    public function clone(string $newCartName, ?string $provider = null): CartService
    {
        $this->loadCart();

        $provider = $provider ?: $this->provider;
        $newCart = $this->manager->cart($newCartName, $provider);

        // If the new cart already exists, clear it first
        if ($newCart->exists()) {
            $newCart->clear();
        }

        // Copy cart data
        $newCartData = $this->cartData;
        $newCartData['name'] = $newCartName;
        $newCartData['created_at'] = now()->toISOString();

        // Copy all items
        foreach ($this->cartData['items'] as $item) {
            $cartableModel = $item['cartable_type'];
            $cartable = $cartableModel::find($item['cartable_id']);

            if ($cartable) {
                $newCart->add($cartable, $item['quantity'], $item['attributes']);
            }
        }

        // Copy configuration
        if (! empty($this->cartData['config'])) {
            $newCart->setConfig($this->cartData['config']);
        }

        // Set user if present
        if ($this->user) {
            $newCart->forUser($this->user);
        }

        return $newCart;
    }

    /**
     * Convert cart to a different provider
     */
    public function convertToProvider(string $newProvider): CartService
    {
        if ($newProvider === $this->provider) {
            return $this;
        }

        $this->loadCart();

        // Create new cart with same name but different provider
        $newCart = $this->manager->cart($this->name, $newProvider);

        // If the cart exists in the new provider, clear it first
        if ($newCart->exists()) {
            $newCart->clear();
        }

        // Copy all items using the add method to ensure proper handling
        foreach ($this->cartData['items'] as $item) {
            $cartableModel = $item['cartable_type'];
            $cartable = $cartableModel::find($item['cartable_id']);

            if ($cartable) {
                $newCart->add($cartable, $item['quantity'], $item['attributes']);
            }
        }

        // Copy configuration
        if (! empty($this->cartData['config'])) {
            $newCart->setConfig($this->cartData['config']);
        }

        // Set user if present
        if ($this->user) {
            $newCart->forUser($this->user);
        }

        if ($this->sessionId) {
            $newCart->forSession($this->sessionId);
        }

        // Delete from old provider
        $this->delete();

        return $newCart;
    }

    /**
     * Set discount for specific item
     */
    public function setItemDiscount(string|int $itemId, array $discountSettings): self
    {
        $this->loadCart();

        foreach ($this->cartData['items'] as $index => $item) {
            if ($item['id'] == $itemId) {
                $this->cartData['items'][$index]['discount_settings'] = $discountSettings;
                $this->save();

                // For database provider, also update CartItem records
                if ($this->provider === 'database') {
                    $this->syncCartItemsToDatabase();
                }

                return $this;
            }
        }

        throw new \HCart\LaravelMultiCart\Exceptions\CartItemNotFoundException($itemId);
    }

    /**
     * Set tax for specific item
     */
    public function setItemTax(string|int $itemId, array $taxSettings): self
    {
        $this->loadCart();

        foreach ($this->cartData['items'] as $index => $item) {
            if ($item['id'] == $itemId) {
                $this->cartData['items'][$index]['tax_settings'] = $taxSettings;
                $this->save();

                // For database provider, also update CartItem records
                if ($this->provider === 'database') {
                    $this->syncCartItemsToDatabase();
                }

                return $this;
            }
        }

        throw new \HCart\LaravelMultiCart\Exceptions\CartItemNotFoundException($itemId);
    }

    /**
     * Set shipping for specific item
     */
    public function setItemShipping(string|int $itemId, array $shippingSettings): self
    {
        $this->loadCart();

        foreach ($this->cartData['items'] as $index => $item) {
            if ($item['id'] == $itemId) {
                $this->cartData['items'][$index]['shipping_settings'] = $shippingSettings;
                $this->save();

                // For database provider, also update CartItem records
                if ($this->provider === 'database') {
                    $this->syncCartItemsToDatabase();
                }

                return $this;
            }
        }

        throw new \HCart\LaravelMultiCart\Exceptions\CartItemNotFoundException($itemId);
    }

    /**
     * Get total discount amount for cart
     */
    public function totalDiscount(): float
    {
        $this->loadCart();
        $totalDiscount = 0;

        foreach ($this->cartData['items'] as $item) {
            $discountSettings = $item['discount_settings'] ?? $this->config->getDiscountSettings();

            if ($discountSettings['enabled'] ?? true) {
                $baseAmount = $item['price'] * $item['quantity'];
                $discountValue = $discountSettings['value'] ?? 0.0;
                $discountType = $discountSettings['type'] ?? 'percentage';

                if ($discountType === 'percentage') {
                    $itemDiscount = $baseAmount * ($discountValue / 100);
                } else {
                    $itemDiscount = $discountValue * $item['quantity'];
                }

                // Apply minimum and maximum limits
                if (isset($discountSettings['minimum_amount']) && $itemDiscount < $discountSettings['minimum_amount']) {
                    $itemDiscount = $discountSettings['minimum_amount'];
                }

                if (isset($discountSettings['maximum_amount']) && $itemDiscount > $discountSettings['maximum_amount']) {
                    $itemDiscount = $discountSettings['maximum_amount'];
                }

                $totalDiscount += $itemDiscount;
            }
        }

        // Apply cart-level discount settings
        $cartDiscountSettings = $this->config->getDiscountSettings();
        if (($cartDiscountSettings['enabled'] ?? true) && ! ($cartDiscountSettings['per_item'] ?? false)) {
            $cartSubtotal = $this->subtotal();

            if (! isset($cartDiscountSettings['minimum_amount']) || $cartSubtotal >= $cartDiscountSettings['minimum_amount']) {
                $cartDiscountValue = $cartDiscountSettings['value'] ?? 0.0;
                $cartDiscountType = $cartDiscountSettings['type'] ?? 'percentage';

                if ($cartDiscountType === 'percentage') {
                    $cartDiscount = $cartSubtotal * ($cartDiscountValue / 100);
                } else {
                    $cartDiscount = $cartDiscountValue;
                }

                if (isset($cartDiscountSettings['maximum_amount']) && $cartDiscount > $cartDiscountSettings['maximum_amount']) {
                    $cartDiscount = $cartDiscountSettings['maximum_amount'];
                }

                $totalDiscount += $cartDiscount;
            }
        }

        return (float) $totalDiscount;
    }

    /**
     * Get total tax amount for cart
     */
    public function totalTax(): float
    {
        $this->loadCart();
        $totalTax = 0;

        // Check if any items have individual tax settings
        $hasItemTaxSettings = false;
        foreach ($this->cartData['items'] as $item) {
            $itemTaxSettings = $item['tax_settings'] ?? [];
            if (($itemTaxSettings['enabled'] ?? true) && ($itemTaxSettings['value'] ?? 0) > 0) {
                $hasItemTaxSettings = true;
                break;
            }
        }

        // Get cart-level tax settings from cart config
        $cartConfig = $this->getConfig();
        $cartTaxSettings = $cartConfig['tax'] ?? $this->config->getTaxSettings();
        $useCartLevelTax = ($cartTaxSettings['enabled'] ?? true) && ! ($cartTaxSettings['per_item'] ?? false) && ! $hasItemTaxSettings;

        if ($useCartLevelTax) {
            $cartSubtotal = $this->subtotal();
            if ($cartTaxSettings['compound'] ?? false) {
                $cartSubtotal -= $this->totalDiscount();
            }
            $cartTaxValue = $cartTaxSettings['value'] ?? 0.0;
            $cartTaxType = $cartTaxSettings['type'] ?? 'percentage';
            if ($cartTaxType === 'percentage') {
                $cartTax = $cartSubtotal * ($cartTaxValue / 100);
            } else {
                $cartTax = $cartTaxValue;
            }

            return (float) $cartTax;
        }

        // Otherwise, sum per-item taxes
        foreach ($this->cartData['items'] as $item) {
            $taxSettings = $item['tax_settings'] ?? [];
            if ($taxSettings && ($taxSettings['enabled'] ?? true)) {
                $baseAmount = $item['price'] * $item['quantity'];
                $taxValue = $taxSettings['value'] ?? 0.0;
                $taxType = $taxSettings['type'] ?? 'percentage';
                if ($taxSettings['compound'] ?? false) {
                    $itemDiscountSettings = $item['discount_settings'] ?? $this->config->getDiscountSettings();
                    if ($itemDiscountSettings['enabled'] ?? true) {
                        $discountValue = $itemDiscountSettings['value'] ?? 0.0;
                        $discountType = $itemDiscountSettings['type'] ?? 'percentage';
                        if ($discountType === 'percentage') {
                            $itemDiscount = $baseAmount * ($discountValue / 100);
                        } else {
                            $itemDiscount = $discountValue * $item['quantity'];
                        }
                        $baseAmount -= $itemDiscount;
                    }
                }
                if ($taxType === 'percentage') {
                    $itemTax = $baseAmount * ($taxValue / 100);
                } else {
                    $itemTax = $taxValue * $item['quantity'];
                }
                $totalTax += $itemTax;
            }
        }

        return (float) $totalTax;
    }

    /**
     * Get total shipping amount for cart
     */
    public function totalShipping(): float
    {
        $this->loadCart();
        $totalShipping = 0;

        foreach ($this->cartData['items'] as $item) {
            $shippingSettings = $item['shipping_settings'] ?? $this->config->getShippingSettings();

            if ($shippingSettings['enabled'] ?? true) {
                $baseAmount = $item['price'] * $item['quantity'];
                $shippingValue = $shippingSettings['value'] ?? 0.0;
                $shippingType = $shippingSettings['type'] ?? 'fixed';

                if ($shippingType === 'percentage') {
                    $itemShipping = $baseAmount * ($shippingValue / 100);
                } elseif ($shippingType === 'per_piece') {
                    // Piece-based shipping calculation
                    $piecesPerShipping = $shippingSettings['pieces_per_shipping'] ?? 1;
                    $maxCharges = $shippingSettings['max_shipping_charges'] ?? null;

                    $charges = ceil($item['quantity'] / $piecesPerShipping);
                    if ($maxCharges !== null && $charges > $maxCharges) {
                        $charges = $maxCharges;
                    }

                    $itemShipping = $shippingValue * $charges;
                } else {
                    $itemShipping = $shippingValue * $item['quantity'];
                }

                $totalShipping += $itemShipping;
            }
        }

        // Apply cart-level shipping settings from cart config
        $cartConfig = $this->getConfig();
        $cartShippingSettings = $cartConfig['shipping'] ?? $this->config->getShippingSettings();
        if (($cartShippingSettings['enabled'] ?? true) && ! ($cartShippingSettings['per_item'] ?? false)) {
            $cartSubtotal = $this->subtotal();

            // Check for free shipping threshold
            if (isset($cartShippingSettings['free_shipping_threshold']) && $cartSubtotal >= $cartShippingSettings['free_shipping_threshold']) {
                return 0.0;
            }

            $cartShippingValue = $cartShippingSettings['value'] ?? 0.0;
            $cartShippingType = $cartShippingSettings['type'] ?? 'fixed';

            if ($cartShippingType === 'percentage') {
                $cartShipping = $cartSubtotal * ($cartShippingValue / 100);
            } else {
                $cartShipping = $cartShippingValue;
            }

            $totalShipping += $cartShipping;
        }

        return (float) $totalShipping;
    }

    /**
     * Check if cart exists
     */
    public function exists(): bool
    {
        return $this->manager->exists($this->name, $this->provider);
    }

    /**
     * Get cart name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get cart provider
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Delete the cart
     */
    public function delete(): bool
    {
        $this->loadCart();
        $cartData = $this->cartData;

        $result = $this->manager->delete($this->name, $this->provider);

        if ($result) {
            event(new \HCart\LaravelMultiCart\Events\CartDeleted($this->name, $cartData, $this->provider));
        }

        return $result;
    }

    /**
     * Load cart data from provider
     */
    protected function loadCart(): void
    {
        if ($this->loaded) {
            return;
        }

        $provider = $this->manager->getProvider($this->provider);

        // Use the regular provider get method
        $data = $provider->get($this->name);

        if ($data === null) {
            $this->cartData = [
                'name' => $this->name,
                'config' => [],
                'user_id' => $this->user?->getKey(),
                'user_type' => $this->user ? get_class($this->user) : null,
                'session_id' => $this->sessionId,
                'items' => [],
                'created_at' => now()->toISOString(),
            ];

            event(new CartCreated(
                $this->name,
                $this->cartData,
                $this->provider,
                $this->user?->getKey(),
                $this->user ? get_class($this->user) : null,
                $this->cartData['config'] ?? []
            ));
        } else {
            $this->cartData = $data;
        }

        $this->loaded = true;
    }

    /**
     * Save cart data to provider
     */
    protected function save(): void
    {
        $this->cartData['updated_at'] = now()->toISOString();
        $this->cartData['items_count'] = count($this->cartData['items']);

        if ($this->user) {
            $this->cartData['user_id'] = $this->user->getKey();
            $this->cartData['user_type'] = get_class($this->user);
        }

        if ($this->sessionId) {
            $this->cartData['session_id'] = $this->sessionId;
        }

        $provider = $this->manager->getProvider($this->provider);
        $provider->put($this->name, $this->cartData);

        event(new CartUpdated($this->name, $this->cartData, $this->provider));
    }

    /**
     * Generate unique key for item
     */
    protected function generateUniqueKey(Model $cartable, array $attributes): string
    {
        $callback = $this->config->getUniqueItemCallback();

        if ($callback) {
            return $callback($cartable->getKey(), get_class($cartable), $attributes);
        }

        // Include attributes in the unique key to make items with different attributes unique
        $attributesString = '';
        if (! empty($attributes)) {
            ksort($attributes); // Sort attributes to ensure consistent JSON encoding
            $attributesString = json_encode($attributes);
        }

        return md5($cartable->getKey().get_class($cartable).$attributesString);
    }

    /**
     * Sync cart items to database (for database provider)
     */
    protected function syncCartItemsToDatabase(): void
    {
        if ($this->provider !== 'database') {
            return;
        }

        $cartId = $this->getCartId();
        if (! $cartId) {
            return;
        }

        $cartItemModel = $this->config->getCartItemModel();

        // Use a single query to get existing items with eager loading
        $existingItems = $cartItemModel::where('cart_id', $cartId)
            ->with('cartable')
            ->get();

        // Create efficient map of existing items by unique key
        $existingItemsMap = $this->createExistingItemsMap($existingItems);

        // Prepare bulk operations
        $itemsToUpdate = [];
        $itemsToCreate = [];
        $processedKeys = [];

        foreach ($this->cartData['items'] as $itemData) {
            $uniqueKey = $itemData['unique_key'] ?? $this->generateUniqueKeyFromItem($itemData);
            $processedKeys[] = $uniqueKey;

            if (isset($existingItemsMap[$uniqueKey])) {
                $itemsToUpdate[] = [
                    'model' => $existingItemsMap[$uniqueKey],
                    'data' => $this->prepareItemUpdateData($itemData),
                ];
            } else {
                $itemsToCreate[] = $this->prepareItemCreateData($cartId, $itemData);
            }
        }

        // Perform bulk operations
        $this->performBulkUpdates($itemsToUpdate);
        $this->performBulkCreates($cartItemModel, $itemsToCreate);
        $this->removeUnprocessedItems($existingItemsMap, $processedKeys);
    }

    /**
     * Create efficient map of existing items by unique key
     */
    private function createExistingItemsMap($existingItems): array
    {
        $existingItemsMap = [];

        foreach ($existingItems as $existingItem) {
            if ($existingItem->cartable) {
                $uniqueKey = $this->generateUniqueKey(
                    $existingItem->cartable,
                    $existingItem->attributes ?? []
                );
                $existingItemsMap[$uniqueKey] = $existingItem;
            }
        }

        return $existingItemsMap;
    }

    /**
     * Prepare data for item update
     */
    private function prepareItemUpdateData(array $itemData): array
    {
        return [
            'quantity' => $itemData['quantity'],
            'price' => $itemData['price'],
            'attributes' => $itemData['attributes'],
            'discount_settings' => $itemData['discount_settings'] ?? null,
            'tax_settings' => $itemData['tax_settings'] ?? null,
            'shipping_settings' => $itemData['shipping_settings'] ?? null,
            'pieces_per_shipping' => $itemData['pieces_per_shipping'] ?? 1,
            'max_shipping_charges' => $itemData['max_shipping_charges'] ?? null,
        ];
    }

    /**
     * Prepare data for item creation
     */
    private function prepareItemCreateData(int $cartId, array $itemData): array
    {
        return [
            'cart_id' => $cartId,
            'cartable_id' => $itemData['cartable_id'],
            'cartable_type' => $itemData['cartable_type'],
            'quantity' => $itemData['quantity'],
            'price' => $itemData['price'],
            'attributes' => json_encode($itemData['attributes'] ?? []),
            'discount_settings' => $itemData['discount_settings'] ? json_encode($itemData['discount_settings']) : null,
            'tax_settings' => $itemData['tax_settings'] ? json_encode($itemData['tax_settings']) : null,
            'shipping_settings' => $itemData['shipping_settings'] ? json_encode($itemData['shipping_settings']) : null,
            'pieces_per_shipping' => $itemData['pieces_per_shipping'] ?? 1,
            'max_shipping_charges' => $itemData['max_shipping_charges'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Perform bulk updates efficiently
     */
    private function performBulkUpdates(array $itemsToUpdate): void
    {
        foreach ($itemsToUpdate as $updateData) {
            $item = $updateData['model'];
            $item->fill($updateData['data']);

            if ($item->isDirty()) {
                $item->save();

                // Recalculate amounts if the model supports it
                if (method_exists($item, 'recalculateAmounts')) {
                    $item->recalculateAmounts();
                    $item->save();
                }
            }
        }
    }

    /**
     * Perform bulk creates efficiently
     */
    private function performBulkCreates(string $cartItemModel, array $itemsToCreate): void
    {
        if (empty($itemsToCreate)) {
            return;
        }

        // Create items individually to properly handle JSON casting and model events
        foreach ($itemsToCreate as $itemData) {
            $cartItem = $cartItemModel::create([
                'cart_id' => $itemData['cart_id'],
                'cartable_id' => $itemData['cartable_id'],
                'cartable_type' => $itemData['cartable_type'],
                'quantity' => $itemData['quantity'],
                'price' => $itemData['price'],
                'attributes' => json_decode($itemData['attributes'], true),
                'discount_settings' => $itemData['discount_settings'] ? json_decode($itemData['discount_settings'], true) : null,
                'tax_settings' => $itemData['tax_settings'] ? json_decode($itemData['tax_settings'], true) : null,
                'shipping_settings' => $itemData['shipping_settings'] ? json_decode($itemData['shipping_settings'], true) : null,
                'pieces_per_shipping' => $itemData['pieces_per_shipping'],
                'max_shipping_charges' => $itemData['max_shipping_charges'],
            ]);

            // Recalculate amounts if the model supports it
            if (method_exists($cartItem, 'recalculateAmounts')) {
                $cartItem->recalculateAmounts();
                $cartItem->save();
            }
        }
    }

    /**
     * Remove items that are no longer in cart
     */
    private function removeUnprocessedItems(array $existingItemsMap, array $processedKeys): void
    {
        $itemsToDelete = [];

        foreach ($existingItemsMap as $uniqueKey => $existingItem) {
            if (! in_array($uniqueKey, $processedKeys)) {
                $itemsToDelete[] = $existingItem->id;
            }
        }

        if (! empty($itemsToDelete)) {
            $existingItem->newQuery()->whereIn('id', $itemsToDelete)->delete();
        }
    }

    /**
     * Generate unique key from item data
     */
    protected function generateUniqueKeyFromItem(array $item): string
    {
        $callback = $this->config->getUniqueItemCallback();

        if ($callback) {
            return $callback($item['cartable_id'], $item['cartable_type'], $item['attributes']);
        }

        // Include attributes in the unique key to make items with different attributes unique
        $attributesString = '';
        if (! empty($item['attributes'])) {
            $attributes = $item['attributes'];
            ksort($attributes); // Sort attributes to ensure consistent JSON encoding
            $attributesString = json_encode($attributes);
        }

        return md5($item['cartable_id'].$item['cartable_type'].$attributesString);
    }

    /**
     * Resolve tax settings for a cartable item
     */
    protected function resolveTaxSettings(Model $cartable, array $attributes): array
    {
        // Priority: 1. Explicit attributes, 2. Model interface, 3. PHP attributes, 4. Default config

        // Check explicit tax settings in attributes
        if (isset($attributes['tax_settings']) && is_array($attributes['tax_settings'])) {
            return $attributes['tax_settings'];
        }

        // Check if model implements TaxableInterface
        if ($cartable instanceof TaxableInterface) {
            $taxSettings = $cartable->getTaxSettings();
            if (! empty($taxSettings)) {
                return array_merge([
                    'type' => $cartable->getTaxType(),
                    'value' => $cartable->getTaxRate(),
                    'included' => $cartable->isTaxIncluded(),
                    'compound' => $cartable->isCompoundTax(),
                    'category' => $cartable->getTaxCategory(),
                ], $taxSettings);
            }

            return [
                'type' => $cartable->getTaxType(),
                'value' => $cartable->getTaxRate(),
                'included' => $cartable->isTaxIncluded(),
                'compound' => $cartable->isCompoundTax(),
                'category' => $cartable->getTaxCategory(),
            ];
        }

        // Check PHP attributes
        $taxConfig = AttributeReader::getTaxConfiguration($cartable);
        if ($taxConfig) {
            return [
                'type' => $taxConfig->type,
                'value' => $taxConfig->rate,
                'included' => $taxConfig->included,
                'compound' => $taxConfig->compound,
                'category' => $taxConfig->category,
                'exemptions' => $taxConfig->exemptions,
                'description' => $taxConfig->description,
            ];
        }

        // Return default tax settings from config
        $defaultTaxSettings = $this->config->getTaxSettings();

        return [
            'type' => $defaultTaxSettings['type'] ?? 'percentage',
            'value' => $defaultTaxSettings['value'] ?? 0.0,
            'included' => $defaultTaxSettings['included'] ?? false,
            'compound' => $defaultTaxSettings['compound'] ?? false,
            'category' => $defaultTaxSettings['category'] ?? null,
        ];
    }

    /**
     * Resolve shipping settings for a cartable item
     */
    protected function resolveShippingSettings(Model $cartable, array $attributes): array
    {
        // Priority: 1. Explicit attributes, 2. Model interface, 3. PHP attributes, 4. Default config

        // Check explicit shipping settings in attributes
        if (isset($attributes['shipping_settings']) && is_array($attributes['shipping_settings'])) {
            return $attributes['shipping_settings'];
        }

        // Check if model implements ShippableInterface
        if ($cartable instanceof ShippableInterface) {
            return [
                'type' => $cartable->getShippingType(),
                'value' => $cartable->getShippingCost(),
                'included' => $cartable->isShippingIncluded(),
                'weight' => $cartable->getShippingWeight(),
                'dimensions' => $cartable->getShippingDimensions(),
                'class' => $cartable->getShippingClass(),
                'zones' => $cartable->getShippingZones(),
                'pieces_per_shipping' => $cartable->getPiecesPerShipping(),
                'max_shipping_charges' => $cartable->getMaxShippingCharges(),
                'free_shipping_threshold' => 0.0,
                'settings' => $cartable->getShippingSettings(),
            ];
        }

        // Check PHP attributes
        $shippingConfig = AttributeReader::getShippingConfiguration($cartable);
        if ($shippingConfig) {
            return [
                'type' => $shippingConfig->type,
                'value' => $shippingConfig->cost,
                'included' => $shippingConfig->included,
                'weight' => $shippingConfig->weight,
                'dimensions' => $shippingConfig->dimensions,
                'class' => $shippingConfig->class,
                'zones' => $shippingConfig->zones,
                'pieces_per_shipping' => $shippingConfig->piecesPerShipping,
                'max_shipping_charges' => $shippingConfig->maxShippingCharges,
                'free_shipping_threshold' => $shippingConfig->freeShippingThreshold,
                'exemptions' => $shippingConfig->exemptions,
                'description' => $shippingConfig->description,
            ];
        }

        // Return default shipping settings
        return [
            'type' => 'fixed',
            'value' => 0.0,
            'included' => false,
            'weight' => 0.0,
            'dimensions' => [],
            'class' => null,
            'zones' => [],
            'pieces_per_shipping' => 1,
            'max_shipping_charges' => null,
            'free_shipping_threshold' => 0.0,
        ];
    }
}
