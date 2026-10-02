<?php

return [
    'cluster_label' => 'Website',
    'v1_demo_link' => 'View Website V1 (Demo)',
    'safety_pwa_link' => 'Claesen safety',
    'projects' => [
        'label' => 'Project',
        'plural_label' => 'Projects',
        'sections' => [
            'details' => 'Project Details',
            'media' => 'Media',
            'settings' => 'Settings',
            'work_details' => 'Work Details / In Action',
        ],
        'fields' => [
            'title' => 'Title',
            'slug' => 'Slug',
            'category' => 'Category',
            'client' => 'Client',
            'location' => 'Location',
            'year' => 'Year',
            'description' => 'Description',
            'published' => 'Published',
            'featured' => 'Featured',
            'order_index' => 'Order Index',
            'order_index_helper' => 'Determines the position on the website (1 = first, 0 = default or at the bottom).',
            'featured_image' => 'Featured Image',
            'gallery' => 'Gallery',
            'detail_gallery' => 'Detail Gallery',
            'work_story' => 'Project Story',
            'challenge' => 'Challenge',
            'solution' => 'Solution',
            'result' => 'Result',
        ],
    ],
    'pages' => [
        'label' => 'Page',
        'plural_label' => 'Pages',
        'sections' => [
            'content' => 'Content',
            'seo' => 'SEO',
            'settings' => 'Settings',
        ],
        'fields' => [
            'title' => 'Title',
            'slug' => 'Slug',
            'content' => 'Content',
            'meta_description' => 'Meta Description',
            'meta_keywords' => 'Meta Keywords',
            'published' => 'Published',
            'published_at' => 'Published At',
            'order_index' => 'Order Index',
        ],
    ],
    'consultation_requests' => [
        'label' => 'Consultation Request',
        'plural_label' => 'Consultation Requests',
        'sections' => [
            'contact' => 'Contact Information',
            'details' => 'Request Details',
            'management' => 'Internal Management',
            'message' => 'Message',
            'status' => 'Status',
        ],
        'fields' => [
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'company' => 'Company',
            'type' => 'Type',
            'project_type' => 'Project Type',
            'message' => 'Message',
            'status' => 'Status',
            'priority' => 'Priority',
            'assigned_to' => 'Assigned To',
            'follow_up_date' => 'Follow-up Date',
            'internal_notes' => 'Internal Notes',
            'tags' => 'Tags',
            'first_response_sla' => 'First response',
        ],
        'categories' => [
            'sport' => 'Sport',
            'industrial' => 'Industrial',
            'public' => 'Public',
        ],
        'types' => [
            'consultation' => 'Consultation',
            'quote' => 'Quote',
            'project' => 'Project',
        ],
        'project_types' => [
            'sport' => 'Sport',
            'industrial' => 'Industrial',
            'public' => 'Public',
            'masts' => 'Masts',
            'other' => 'Other',
        ],
        'status_options' => [
            'new' => 'New',
            'assigned' => 'Assigned',
            'in_progress' => 'In Progress',
            'waiting_client' => 'Waiting on Client',
            'closed' => 'Closed',
            'spam' => 'Spam',
        ],
        'priority_options' => [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'urgent' => 'Urgent',
        ],
        'actions' => [
            'export' => 'Export CSV',
            'erase' => 'Erase (GDPR)',
        ],
    ],
    'activities' => [
        'label' => 'Activity',
        'plural_label' => 'Activities',
        'sections' => [
            'general' => 'General',
        ],
        'fields' => [
            'title' => 'Title',
            'type' => 'Type',
            'user' => 'User',
            'date' => 'Date',
            'description' => 'Description',
            'old_value' => 'Old Value',
            'new_value' => 'New Value',
        ],
        'types' => [
            'status_change' => 'Status Change',
            'priority_change' => 'Priority Change',
            'assignment_change' => 'Assignment',
            'follow_up_update' => 'Follow-up Updated',
            'comment' => 'Comment',
            'created' => 'Created',
            'reminder_triggered' => 'Reminder Triggered',
        ],
        'logs' => [
            'created' => 'New consultation request received from :source',
            'status_change' => 'Status changed from :old to :new',
            'priority_change' => 'Priority updated to :priority',
            'assignment_change' => 'Assigned to :user',
            'follow_up_update' => 'Follow-up date set to :date',
            'comment' => 'Internal notes updated',
            'reminder_triggered' => 'Reminder triggered: :title',
        ],
        'notifications' => [
            'new_request_title' => 'New Consultation Request',
            'new_request_body' => ':name has submitted a new request.',
            'reminder_due_title' => 'Reminder Due',
            'reminder_due_body' => ':title — :name',
        ],
    ],
    'publication' => [
        'status' => [
            'idle'     => 'No changes',
            'pending'  => 'Queued',
            'accepted' => 'Publication requested',
            'error'    => 'Publication error',
        ],
        'widget' => [
            'status_label'  => 'Publication Status',
            'last_accepted' => 'Last Request',
            'build_status'  => 'Frontend Build Status',
            'building'      => 'Building...',
            'unreachable'   => 'Unreachable',
            'no_data'       => '—',
            'release'       => 'Release: :release',
        ],
        'actions' => [
            'publish_now'                => 'Publish now',
            'publish_now_confirm_title'  => 'Start publication',
            'publish_now_confirm_body'   => 'This will request a full rebuild of the website. The current version stays live until the build completes.',
            'publish_now_success'        => 'Publication requested',
        ],
    ],
    // CLA-481 (media slots): this block was missing from every language file, so the panel
    // printed `website.media_slots.plural_label` verbatim — including in the cluster's tab
    // bar. The wording follows the code's own vocabulary (a slot is a stable name such as
    // `home.hero` pointing at a project's media) rather than a free interpretation; if the
    // team behind that screen prefers other words, this is what to change.
    'media_slots' => [
        'label' => 'Media slot',
        'plural_label' => 'Media slots',
        'fields' => [
            'slot' => 'Slot',
            'slot_helper' => 'The name the website uses, for example home.hero. Changing it means changing the website too.',
            'media' => 'Media from a project',
            'media_invalid' => 'This media does not belong to a project of this site.',
        ],
    ],

    'page_publication_review' => [
        'label' => 'Page publications',
        'fields' => [
            'page' => 'Page',
            'locale' => 'Locale',
            'status' => 'Status',
            'reviewed_at' => 'Reviewed',
            'reviewed_by' => 'Reviewed by',
        ],
        // The internal vocabulary, in the words of the person approving: the state
        // reads «Reviewed» because the `reviewed_at` column already does, instead of
        // inventing a second word for the same thing.
        'statuses' => [
            'machine' => 'Draft',
            'reviewed' => 'Reviewed',
            'published' => 'Published',
        ],
        // ISO codes mean nothing to the person approving a translation.
        'locales' => [
            'nl' => 'Dutch',
            'fr' => 'French',
            'en' => 'English',
            'de' => 'German',
        ],
        // Copied from the site's own menu (electrobertels-official,
        // src/content/chrome/en.yaml): the names the client already approved for those
        // pages, not names invented by this screen. If they change there, copy them
        // again: the backend does not read that repository.
        'pages' => [
            'home' => 'Home',
            'particulieren' => 'Residential',
            'bedrijven' => 'Business & industry',
            'winkel' => 'Shop',
            'projecten' => 'Projects',
            'over-ons' => 'About us',
            'contact' => 'Contact & quote request',
        ],

        'actions' => [
            'approve' => 'Approve',
            'publish' => 'Publish',
            'retire' => 'Retire',
            'publish_confirm' => 'This page will become indexable in this locale. Continue?',
            'retire_confirm' => 'This page will stop being indexable in this locale. Continue?',
        ],
        'notifications' => [
            'updated' => 'Publication status updated',
        ],
    ],

    'translation_review' => [
        'label' => 'Translation review',
        'fields' => [
            'subject' => 'Subject',
            'attribute' => 'Field',
            'locale' => 'Locale',
            'status' => 'Status',
            'error' => 'Error',
            'updated_at' => 'Updated',
        ],
        'actions' => [
            'edit' => 'Edit',
            'edit_value' => 'Translation (:locale)',
            'approve' => 'Approve',
            'publish' => 'Publish',
            'retranslate' => 'Retranslate (AI)',
            'retranslate_confirm' => 'The approved value will be overwritten by a fresh AI translation and must be approved again. Continue?',
        ],
        'notifications' => [
            'updated' => 'Translation status updated',
        ],
    ],

    'announcements' => [
        'label' => 'Announcement',
        'plural_label' => 'Announcements',
        'message_helper' => 'Shown in the current language of this admin view.',
        'starts_at_helper' => 'Empty = active immediately once published.',
        'ends_at_helper' => 'Empty = stays active until manually archived.',
        'status' => [
            'draft' => 'Draft',
            'published' => 'Published',
            'archived' => 'Archived',
        ],
    ],
    'site_settings' => [
        'label' => 'Site settings',
        'save' => 'Save',
        'saved' => 'Settings saved',
        'fields' => [
            'hours' => 'Opening hours',
            'phone' => 'Phone',
            'email' => 'Email',
            'address' => 'Address',
            'social_links' => 'Social media links (JSON)',
            'legal_name' => 'Legal name',
            'founded_year' => 'Founding year',
            'phone_display' => 'Phone (display)',
            'phone_tel' => 'Phone (tel: link)',
            'whatsapp_display' => 'WhatsApp (display)',
            'whatsapp_url' => 'WhatsApp (URL)',
            'address_structured' => 'Address (structured)',
            'address_country' => 'Country (translatable)',
            'maps_embed_url' => 'Maps embed (URL)',
            'maps_directions_url' => 'Maps directions (URL)',
            'maps_consent_mode' => 'Maps consent',
            'vat_number' => 'VAT number',
            'opening_hours' => 'Opening hours (structured)',
            'contact_consent_version' => 'Contact form consent version',
        ],
        'hints' => [
            'address' => 'Claesen: verbatim string, read unchanged by the Claesen frontend. Do not edit for Electro Bertels.',
            'address_structured' => 'Electro Bertels: structured source — JSON with street, postal_code, city, country_code. The Claesen frontend does not read this field.',
            'founded_year' => 'JSON integer, e.g. 1977',
            'opening_hours' => 'JSON array: [{"day_key": "monday", "hours": [{"open": "09:00", "close": "18:00"}] | null}]',
            'maps_consent_mode' => 'always | on_consent | never',
        ],
    ],
];
