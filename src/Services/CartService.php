<?php

namespace HCart\LaravelMultiCart\Services;

use HCart\LaravelMultiCart\Contracts\CartConfigInterface;
use HCart\LaravelMultiCart\Contracts\CartInterface;
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
        $this->loadCart();

        $price = $cartable->getCartPrice();
        $uniqueKey = $this->generateUniqueKey($cartable, $attributes);

        // Check for existing item
        $existingIndex = null;
        foreach ($this->cartData['items'] as $index => $item) {
            if ($this->generateUniqueKeyFromItem($item) === $uniqueKey) {
                $existingIndex = $index;
                break;
            }
        }

        if ($existingIndex !== null && $this->config->shouldPreventDuplicates()) {
            // Update existing item quantity
            $this->cartData['items'][$existingIndex]['quantity'] += $quantity;
            $item = $this->cartData['items'][$existingIndex];
        } else {
            // Add new item
            $item = [
                'id' => uniqid(),
                'cartable_id' => $cartable->getKey(),
                'cartable_type' => get_class($cartable),
                'quantity' => $quantity,
                'price' => $price,
                'attributes' => $attributes,
                'unique_key' => $uniqueKey,
            ];

            $this->cartData['items'][] = $item;
        }

        $this->save();

        // For database provider, also create/update CartItem records
        if ($this->provider === 'database') {
            $this->syncCartItemsToDatabase();
        }

        event(new ItemAdded($this->name, $cartable, $quantity, $attributes, $item));

        return $this;
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
     * Get total price including tax
     */
    public function total(): float
    {
        return $this->subtotal() + $this->tax();
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
     * Get tax amount
     */
    public function tax(): float
    {
        return $this->subtotal() * $this->config->getTaxRate();
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

        // For database provider with user context, get user-specific cart
        if ($this->provider === 'database' && $this->user) {
            $cartModel = app('LaravelMultiCart.config')->getCartModel();
            $dbCart = $cartModel::where('name', $this->name)
                ->where('user_id', $this->user->getKey())
                ->where('user_type', get_class($this->user))
                ->first();

            if ($dbCart) {
                // Manually construct the cart data from the database record
                $cartData = $dbCart->config;
                $data = array_merge($cartData, [
                    'id' => $dbCart->id,
                    'user_id' => $dbCart->user_id,
                    'user_type' => $dbCart->user_type,
                    'session_id' => $dbCart->session_id,
                    'expires_at' => $dbCart->expires_at?->toISOString(),
                ]);
            } else {
                $data = null;
            }
        } else {
            $data = $provider->get($this->name);
        }

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

        return md5($cartable->getKey().get_class($cartable));
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

        // Get current CartItem records
        $cartItemModel = $this->config->getCartItemModel();
        $existingItems = $cartItemModel::where('cart_id', $cartId)->get();

        // Create a map of existing items by their unique key
        $existingItemsMap = [];
        foreach ($existingItems as $existingItem) {
            $cartableModel = $existingItem->cartable_type;
            $cartable = $cartableModel::find($existingItem->cartable_id);
            if ($cartable) {
                $uniqueKey = $this->generateUniqueKey($cartable, $existingItem->attributes ?? []);
                $existingItemsMap[$uniqueKey] = $existingItem;
            }
        }

        // Update or create CartItem records based on cart data
        $processedKeys = [];
        foreach ($this->cartData['items'] as $itemData) {
            $uniqueKey = $itemData['unique_key'] ?? $this->generateUniqueKeyFromItem($itemData);
            $processedKeys[] = $uniqueKey;

            if (isset($existingItemsMap[$uniqueKey])) {
                // Update existing item
                $existingItem = $existingItemsMap[$uniqueKey];
                $existingItem->update([
                    'quantity' => $itemData['quantity'],
                    'price' => $itemData['price'],
                    'attributes' => $itemData['attributes'],
                ]);
            } else {
                // Create new item
                $cartItemModel::create([
                    'cart_id' => $cartId,
                    'cartable_id' => $itemData['cartable_id'],
                    'cartable_type' => $itemData['cartable_type'],
                    'quantity' => $itemData['quantity'],
                    'price' => $itemData['price'],
                    'attributes' => $itemData['attributes'],
                ]);
            }
        }

        // Remove CartItem records that are no longer in the cart
        foreach ($existingItemsMap as $uniqueKey => $existingItem) {
            if (! in_array($uniqueKey, $processedKeys)) {
                $existingItem->delete();
            }
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

        return md5($item['cartable_id'].$item['cartable_type']);
    }
}
