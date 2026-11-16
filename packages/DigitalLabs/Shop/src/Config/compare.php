<?php

return [
    /*
     * The most products a compare list holds, for guests and signed-in customers alike (owner
     * decision, 2026-11-16). Enforced by the compare add endpoint (API\CompareController::store)
     * and by the shared guest helper (views/components/compare/list.blade.php).
     */
    'limit' => 4,
];
