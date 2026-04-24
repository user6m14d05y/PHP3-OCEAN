<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductReviewUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $productId;
    public $action;
    public $reviewId;
    public $productRating;

    /**
     * Create a new event instance.
     */
    public function __construct($productId, $action, $reviewId, $productRating)
    {
        $this->productId = $productId;
        $this->action = $action;
        $this->reviewId = $reviewId;
        $this->productRating = $productRating;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('product.' . $this->productId),
        ];
    }
    
    public function broadcastAs(): string
    {
        return 'ProductReviewUpdatedEvent';
    }
}
