<?php

return [
    // A malfunction has a validated cycle (KNX-56); this is the 422 when a step is skipped.
    'transition_invalid' => 'Deze storingsovergang is niet toegestaan (:from → :to).',
    'status_not_for_lifecycle' => 'Deze status hoort niet bij dit type conflict.',
];
