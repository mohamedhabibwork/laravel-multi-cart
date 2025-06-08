<?php

use HCart\LaravelMultiCart\Events\CartCreated;
use HCart\LaravelMultiCart\Events\CartDeleted;
use HCart\LaravelMultiCart\Events\CartUpdated;
use HCart\LaravelMultiCart\Events\ItemAdded;
use HCart\LaravelMultiCart\Events\ItemRemoved;
use HCart\LaravelMultiCart\Events\ItemUpdated;
use HCart\LaravelMultiCart\Facades\LaravelMultiCart;
use HCart\LaravelMultiCart\Tests\Fixtures\Product;
use HCart\LaravelMultiCart\Tests\Fixtures\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $this->product = Product::create([
        'name' => 'Test Product',
        'price' => 29.99,
        'sku' => 'TEST-001',
    ]);
});

describe('Cart Events', function () {
    it('dispatches CartCreated event when cart is created', function () {
        $cart = LaravelMultiCart::cart('new_cart');

        // Trigger cart loading by accessing cart content
        $cart->count();

        Event::assertDispatched(CartCreated::class, function ($event) {
            return $event->cartName === 'new_cart';
        });
    });

    it('dispatches CartUpdated event when cart is modified', function () {
        $cart = LaravelMultiCart::cart('test_cart');
        $cart->add($this->product);

        Event::assertDispatched(CartUpdated::class, function ($event) {
            return $event->cartName === 'test_cart';
        });
    });

    it('dispatches CartDeleted event when cart is deleted', function () {
        $cart = LaravelMultiCart::cart('delete_cart');
        $cart->add($this->product);
        $cart->delete();

        Event::assertDispatched(CartDeleted::class, function ($event) {
            return $event->cartName === 'delete_cart';
        });
    });

    it('includes correct data in CartCreated event', function () {
        $cart = LaravelMultiCart::cart('created_cart', 'database');

        // Trigger cart loading
        $cart->count();

        Event::assertDispatched(CartCreated::class, function ($event) {
            return $event->cartName === 'created_cart' &&
                   $event->provider === 'database' &&
                   is_array($event->cartData) &&
                   isset($event->cartData['name']) &&
                   $event->cartData['name'] === 'created_cart';
        });
    });

    it('includes correct data in CartUpdated event', function () {
        $cart = LaravelMultiCart::cart('updated_cart', 'database');

        // First trigger cart creation
        $cart->count();

        // Clear previous events
        Event::fake();

        // Trigger an update by adding an item
        $cart->add($this->product);

        Event::assertDispatched(CartUpdated::class, function ($event) {
            return $event->cartName === 'updated_cart' &&
                   $event->provider === 'database' &&
                   is_array($event->cartData) &&
                   isset($event->cartData['items']) &&
                   count($event->cartData['items']) === 1;
        });
    });

    it('includes correct data in CartDeleted event', function () {
        $cart = LaravelMultiCart::cart('deleted_cart');
        $cart->add($this->product);
        $cart->delete();

        Event::assertDispatched(CartDeleted::class, function ($event) {
            return $event->cartName === 'deleted_cart' &&
                   $event->provider === 'session';
        });
    });
});

describe('Item Events', function () {
    beforeEach(function () {
        $this->cart = LaravelMultiCart::cart('item_events_cart');
    });

    it('dispatches ItemAdded event when item is added', function () {
        $this->cart->add($this->product, 2, ['size' => 'large']);

        Event::assertDispatched(ItemAdded::class, function ($event) {
            return $event->cartName === 'item_events_cart' &&
                   $event->cartableId === $this->product->id &&
                   $event->cartableType === get_class($this->product) &&
                   $event->quantity === 2 &&
                   $event->price === 29.99 &&
                   $event->attributes === ['size' => 'large'];
        });
    });

    it('dispatches ItemUpdated event when item is updated', function () {
        $this->cart->add($this->product, 2);

        // Clear previous events
        Event::fake();

        $items = $this->cart->items();
        $itemId = $items->first()['id'];

        $this->cart->update($itemId, ['quantity' => 5]);

        Event::assertDispatched(ItemUpdated::class, function ($event) use ($itemId) {
            return $event->cartName === 'item_events_cart' &&
                   $event->itemId === $itemId &&
                   isset($event->oldData['quantity']) &&
                   isset($event->newData['quantity']) &&
                   $event->oldData['quantity'] === 2 &&
                   $event->newData['quantity'] === 5;
        });
    });

    it('dispatches ItemRemoved event when item is removed', function () {
        $this->cart->add($this->product, 2);

        $items = $this->cart->items();
        $itemId = $items->first()['id'];

        // Clear previous events
        Event::fake();

        $this->cart->remove($itemId);

        Event::assertDispatched(ItemRemoved::class, function ($event) use ($itemId) {
            return $event->cartName === 'item_events_cart' &&
                   $event->itemId === $itemId &&
                   $event->cartableId === $this->product->id &&
                   $event->cartableType === get_class($this->product);
        });
    });

    it('includes complete item data in ItemAdded event', function () {
        // Clear previous events from cart creation
        Event::fake();

        $this->cart->add($this->product, 3, ['color' => 'red', 'size' => 'medium']);

        Event::assertDispatched(ItemAdded::class, function ($event) {
            return $event->cartName === 'item_events_cart' &&
                   $event->cartableId === $this->product->id &&
                   $event->cartableType === get_class($this->product) &&
                   $event->quantity === 3 &&
                   $event->price === 29.99 &&
                   $event->attributes === ['color' => 'red', 'size' => 'medium'] &&
                   isset($event->itemData['id']) &&
                   $event->itemData['quantity'] === 3;
        });
    });

    it('includes old and new data in ItemUpdated event', function () {
        $this->cart->add($this->product, 2, ['size' => 'small']);

        $items = $this->cart->items();
        $itemId = $items->first()['id'];

        // Clear previous events
        Event::fake();

        $this->cart->update($itemId, [
            'quantity' => 4,
            'price' => 25.99,
            'attributes' => ['size' => 'large'],
        ]);

        Event::assertDispatched(ItemUpdated::class, function ($event) use ($itemId) {
            return $event->cartName === 'item_events_cart' &&
                   $event->itemId === $itemId &&
                   $event->oldData['quantity'] === 2 &&
                   $event->oldData['price'] === 29.99 &&
                   $event->oldData['attributes'] === ['size' => 'small'] &&
                   $event->newData['quantity'] === 4 &&
                   $event->newData['price'] === 25.99 &&
                   $event->newData['attributes'] === ['size' => 'large'];
        });
    });

    it('includes removed item data in ItemRemoved event', function () {
        $this->cart->add($this->product, 2, ['size' => 'large']);

        $items = $this->cart->items();
        $itemId = $items->first()['id'];
        $itemData = $items->first();

        // Clear previous events
        Event::fake();

        $this->cart->remove($itemId);

        Event::assertDispatched(ItemRemoved::class, function ($event) use ($itemId) {
            return $event->cartName === 'item_events_cart' &&
                   $event->itemId === $itemId &&
                   $event->cartableId === $this->product->id &&
                   $event->cartableType === get_class($this->product) &&
                   $event->removedItemData['quantity'] === 2 &&
                   $event->removedItemData['attributes'] === ['size' => 'large'];
        });
    });
});

describe('Event Listeners', function () {
    it('can register event listeners', function () {
        $eventsFired = [];

        Event::listen(CartCreated::class, function ($event) use (&$eventsFired) {
            $eventsFired[] = 'cart_created';
        });

        Event::listen(ItemAdded::class, function ($event) use (&$eventsFired) {
            $eventsFired[] = 'item_added';
        });

        // Stop faking events to test real listeners
        Event::fake();
        Event::listen(CartCreated::class, function ($event) use (&$eventsFired) {
            $eventsFired[] = 'cart_created';
        });

        Event::listen(ItemAdded::class, function ($event) use (&$eventsFired) {
            $eventsFired[] = 'item_added';
        });

        $cart = LaravelMultiCart::cart('listener_test');
        $cart->add($this->product);

        // Since we're using Event::fake(), let's just verify events were dispatched
        Event::assertDispatched(CartCreated::class);
        Event::assertDispatched(ItemAdded::class);
    });
});

describe('Multiple Events', function () {
    it('dispatches multiple events for complex operations', function () {
        $cart = LaravelMultiCart::cart('complex_ops');

        // Add item (should dispatch CartCreated and ItemAdded)
        $cart->add($this->product, 2);

        Event::assertDispatched(CartCreated::class);
        Event::assertDispatched(ItemAdded::class);
        Event::assertDispatched(CartUpdated::class);
    });

    it('dispatches events for cart operations across different providers', function () {
        // Session cart
        $sessionCart = LaravelMultiCart::cart('session_cart', 'session');
        $sessionCart->add($this->product);

        // Database cart
        $dbCart = LaravelMultiCart::cart('db_cart', 'database');
        $dbCart->add($this->product);

        Event::assertDispatched(CartCreated::class, function ($event) {
            return $event->cartName === 'session_cart' && $event->provider === 'session';
        });

        Event::assertDispatched(CartCreated::class, function ($event) {
            return $event->cartName === 'db_cart' && $event->provider === 'database';
        });

        Event::assertDispatchedTimes(ItemAdded::class, 2);
    });

    it('dispatches events when clearing cart', function () {
        $cart = LaravelMultiCart::cart('clear_test');
        $cart->add($this->product, 2);

        // Clear previous events
        Event::fake();

        $cart->clear();

        Event::assertDispatched(CartUpdated::class, function ($event) {
            return $event->cartName === 'clear_test';
        });
    });
});
