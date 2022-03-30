<?php namespace GeneaLabs\LaravelPivotEvents\Traits;

use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Support\Arr;

trait ExtendFireModelEventTrait
{
    /**
     * Fire the given event for the model.
     *
     * @param string $event
     * @param bool   $halt
     *
     * @return mixed
     */
    public function fireModelEvent(
        $event,
        $halt = true,
        $relationName = null,
        $ids = [],
        $idsAttributes = []
    ) {
        if (!isset(static::$dispatcher)) {
            return true;
        }

        $method = $halt
            ? 'until'
            : 'dispatch';

        $result = $this->filterModelEventResults(
            $this->fireCustomModelEvent($event, $method)
        );

        if (false === $result) {
            return false;
        }

        $payload = [
            0 => $this,
            'model' => $this,
            'relation' => $relationName,
            'pivotIds' => $ids,
            'pivotIdsAttributes' => $idsAttributes
        ];
        $result = $result
            ?: static::$dispatcher
                ->{$method}("eloquent.{$event}: " . static::class, $payload);
        $this->broadcastPivotEvent($event, $payload);

        return $result;
    }

    protected function broadcastPivotEvent(string $event, array $payload): void
    {
        $events = [
            "pivotAttached",
            "pivotDetached",
            "pivotUpdated",
        ];

        if (! in_array($event, $events)) {
            return;
        }

        $name = method_exists($this, "broadcastAs")
                ? $this->broadcastAs()
                : $event;
        $channels = method_exists($this, "broadcastOn")
            ? Arr::wrap($this->broadcastOn($event))
            : [];

        if (empty($channels)) {
            return;
        }

        $connections = method_exists($this, "broadcastConnections")
            ? $this->broadcastConnections()
            : [null];
        $manager = app(BroadcastingFactory::class);

        foreach ($connections as $connection) {
            $manager->connection($connection)
                ->broadcast($channels, $name, $payload);
        }
    }
}
