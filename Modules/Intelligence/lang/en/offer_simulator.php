<?php

return [
    'navigation_label' => 'Offer Simulator',
    'title'            => 'AI Offer Simulator',
    'sections' => [
        'project_details' => 'Project Details',
    ],
    'fields' => [
        'description'             => 'Project Description',
        'description_placeholder' => 'Describe the project...',
        'category'                => 'Category',
        'zipcode'                 => 'Zipcode',
        'complexity'              => 'Complexity Factor',
        'complexity_hint'         => 'AI suggests this, but you can override.',
    ],
    'actions' => [
        'simulate' => 'Simulate Offer',
    ],
    'notifications' => [
        'off_topic_title' => 'Off-topic detected',
        'off_topic_body'  => 'Please only enter requests related to lighting or electricity.',
        'gibberish_title' => 'Nonsense detected',
        'gibberish_body'  => 'The Lead Architect is not impressed. Please try again with a real project.',
    ],
];
