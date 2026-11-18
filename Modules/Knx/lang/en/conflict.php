<?php

return [
    // A malfunction has a validated cycle (KNX-56); this is the 422 when a step is skipped.
    'transition_invalid' => 'This malfunction transition is not allowed (:from → :to).',
    'status_not_for_lifecycle' => 'This status does not belong to this conflict type.',
];
