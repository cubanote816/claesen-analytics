<?php

return [
    // CLA-647: a short phrase now, not a single word ("OR") — Microsoft comes first, this is
    // the transition into the password form below it.
    'divider_or' => 'ou connectez-vous avec votre e-mail et votre mot de passe',
    'microsoft_login' => 'Se connecter avec Microsoft',
    // CLA-601: dual-brand login copy, both companies, one backoffice. CLA-647: the two
    // per-company cards and the lead paragraph are gone — one headline says it all.
    'brand_logo_claesen_alt' => 'Claesen Outdoor Lighting',
    'brand_logo_bertels_alt' => 'Electro Bertels',
    // CLA-647 (follow-up): split into two lines of different weight — a single-size,
    // single-weight headline let both company names dominate the screen and compete
    // with the form's own "Sign in". "Staff portal" stays the big line; who it's for
    // is a smaller line right under it, inside the same <h1> (one heading, read together).
    'brand_headline' => 'Portail du personnel',
    'brand_headline_detail' => 'pour Claesen et Electro Bertels',
    'brand_footer' => '© :year · Tous droits réservés',
    'heading' => 'Connexion',
    'email_placeholder' => 'vous@entreprise.com',
    'password_placeholder' => 'Saisissez votre mot de passe',
    'remember' => 'Se souvenir de moi pendant 30 jours',
    'password_show' => 'AFFICHER',
    'password_hide' => 'MASQUER',

    // CLA-603: the mockup's password screens (forgot / sent / reset / done).
    'reset_code_invalid' => 'Ce lien de réinitialisation est invalide ou a expiré.',
    'forgot_password_link' => 'Mot de passe oublié ?',
    'forgot_heading' => 'Mot de passe oublié ?',
    'forgot_subheading' => 'Saisissez l\'adresse e-mail liée à votre compte et nous vous enverrons un lien pour le réinitialiser.',
    'forgot_submit' => 'Envoyer le lien de réinitialisation',
    'reset_use_different_email' => 'Utiliser une autre adresse e-mail',
    'reset_back_to_login' => 'Retour à la connexion',
    'reset_sent_heading' => 'Consultez votre boîte de réception',
    'reset_sent_body' => 'S\'il existe un compte pour :email, vous recevrez sous peu un lien pour réinitialiser votre mot de passe.',
    'reset_new_heading' => 'Définir un nouveau mot de passe',
    'reset_new_subheading' => 'Choisissez un mot de passe que vous n\'avez jamais utilisé. Vous l\'utiliserez désormais pour vous connecter.',
    'reset_new_password_label' => 'Nouveau mot de passe',
    'reset_confirm_password_label' => 'Confirmer le nouveau mot de passe',
    'reset_password_hint' => 'Au moins :min caractères.',
    'reset_passwords_mismatch' => 'Les deux mots de passe ne correspondent pas.',
    'reset_show_passwords' => 'Afficher les mots de passe',
    'reset_hide_passwords' => 'Masquer les mots de passe',
    'reset_submit_new' => 'Mettre à jour le mot de passe',
    'reset_done_heading' => 'Mot de passe mis à jour',
    'reset_done_body' => 'Votre mot de passe a été modifié. Connectez-vous avec votre nouveau mot de passe pour continuer.',
    'reset_done_action' => 'Aller à la connexion',
];
