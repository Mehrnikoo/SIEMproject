<?php
/**
 * Events Controller - Lightweight APIs for events polling
 */
class EventsController {
    private $eventModel;

    public function __construct($eventModel) {
        $this->eventModel = $eventModel;
    }

    /**
     * Returns a summary of real events for quick polling.
     * Response shape: { count: number, ids: number[] }
     */
    public function realSummary() {
        header('Content-Type: application/json');
        $events = $this->eventModel->loadRealEvents();
        if (!is_array($events)) {
            echo json_encode(['count' => 0, 'ids' => []]);
            return;
        }
        $ids = [];
        foreach ($events as $e) {
            if (isset($e['id'])) {
                $ids[] = $e['id'];
            }
        }
        echo json_encode([
            'count' => count($events),
            'ids' => $ids,
        ]);
    }
}
