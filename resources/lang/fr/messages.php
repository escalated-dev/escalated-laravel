<?php

return [

    'ticket' => [
        'reply_sent' => 'Réponse envoyée.',
        'note_added' => 'Note ajoutée.',
        'assigned' => 'Ticket assigné.',
        'status_updated' => 'Statut mis à jour.',
        'priority_updated' => 'Priorité mise à jour.',
        'tags_updated' => 'Étiquettes mises à jour.',
        'department_updated' => 'Département mis à jour.',
        'macro_applied' => 'Macro « :name » appliquée.',
        'custom_action_dispatched' => 'Action personnalisée déclenchée.',
        'following' => 'Vous suivez le ticket.',
        'unfollowed' => 'Vous ne suivez plus le ticket.',
        'only_internal_notes_pinned' => 'Seules les notes internes peuvent être épinglées.',
        'updated' => 'Ticket mis à jour.',
        'created' => 'Ticket créé avec succès.',
        'closed' => 'Ticket fermé.',
        'reopened' => 'Ticket réouvert.',
        'customers_cannot_close' => 'Les clients ne peuvent pas fermer les tickets.',
    ],

    'guest' => [
        'created' => 'Ticket créé. Enregistrez ce lien pour vérifier le statut de votre ticket.',
        'ticket_closed' => 'Ce ticket est fermé.',
    ],

    'bulk' => [
        'updated' => ':count ticket(s) mis à jour.',
    ],

    'rating' => [
        'only_resolved_closed' => 'Vous ne pouvez évaluer que les tickets résolus ou fermés.',
        'already_rated' => 'Ce ticket a déjà été évalué.',
        'thanks' => 'Merci pour vos commentaires !',
    ],

    'canned_response' => [
        'created' => 'Réponse prédéfinie créée.',
        'updated' => 'Réponse prédéfinie mise à jour.',
        'deleted' => 'Réponse prédéfinie supprimée.',
    ],

    'department' => [
        'created' => 'Département créé.',
        'updated' => 'Département mis à jour.',
        'deleted' => 'Département supprimé.',
    ],

    'tag' => [
        'created' => 'Étiquette créée.',
        'updated' => 'Étiquette mise à jour.',
        'deleted' => 'Étiquette supprimée.',
    ],

    'macro' => [
        'created' => 'Macro créée.',
        'updated' => 'Macro mise à jour.',
        'deleted' => 'Macro supprimée.',
    ],

    'escalation_rule' => [
        'created' => 'Règle créée.',
        'updated' => 'Règle mise à jour.',
        'deleted' => 'Règle supprimée.',
    ],

    'sla_policy' => [
        'created' => 'Politique SLA créée.',
        'updated' => 'Politique SLA mise à jour.',
        'deleted' => 'Politique SLA supprimée.',
    ],

    'settings' => [
        'updated' => 'Paramètres mis à jour.',
        'invalid_widget_origin' => '« :origin » n’est pas une origine de site. Utilisez https://example.com ou https://*.example.com.',
    ],

    'plugin' => [
        'uploaded' => 'Plugin téléchargé avec succès. Vous pouvez maintenant l\'activer.',
        'upload_failed' => 'Échec du téléchargement du plugin : :error',
        'activated' => 'Plugin activé avec succès.',
        'activate_failed' => 'Échec de l\'activation du plugin : :error',
        'deactivated' => 'Plugin désactivé avec succès.',
        'deactivate_failed' => 'Échec de la désactivation du plugin : :error',
        'composer_delete' => 'Les plugins Composer ne peuvent pas être supprimés. Supprimez le paquet via Composer.',
        'deleted' => 'Plugin supprimé avec succès.',
        'delete_failed' => 'Échec de la suppression du plugin : :error',
    ],

    'errors' => [
        '403' => 'Vous n\'êtes pas autorisé à effectuer cette action.',
        '404' => 'La ressource demandée est introuvable.',
        '419' => 'Votre session a expiré. Actualisez la page et réessayez.',
        '429' => 'Trop de requêtes. Patientez un instant et réessayez.',
        '500' => 'Une erreur s\'est produite de notre côté.',
        '503' => 'Le service est temporairement indisponible. Réessayez sous peu.',
    ],

    'middleware' => [
        'not_admin' => 'Vous n\'êtes pas autorisé en tant qu\'administrateur de support.',
        'not_agent' => 'Vous n\'êtes pas autorisé en tant qu\'agent de support.',
    ],

    'inbound_email' => [
        'disabled' => 'Le courrier entrant est désactivé.',
        'unknown_adapter' => 'Adaptateur inconnu.',
        'invalid_signature' => 'Signature non valide.',
        'processing_failed' => 'Échec du traitement.',
    ],

    'agent_notifications' => [
        'ticket_assigned' => ':reference vous a été attribué',
        'ticket_reply' => 'Nouvelle réponse sur :reference',
        'mention' => ':name vous a mentionné sur :reference',
        'sla_warning' => 'SLA bientôt dépassé sur :reference',
        'sla_breached' => 'SLA dépassé sur :reference',
        'chat_transferred' => 'Une discussion vous a été transférée (:reference)',
        'new_ticket' => 'Nouveau ticket :reference',
        'ticket_escalated' => ':reference a été escaladé',
        'status_changed' => 'Statut modifié sur :reference',
        'generic' => 'Mise à jour sur :reference',
        'unavailable' => 'Un ticket que vous ne pouvez plus consulter',
    ],

    'agent_inbox' => [
        'queues' => [
            'mine' => 'Ma boîte',
            'unassigned' => 'Non assignés',
            'all' => 'Tous les tickets',
        ],
        'views' => [
            'urgent' => 'Urgents',
            'sla_at_risk' => 'SLA à risque',
            'waiting_on_me' => 'En attente de moi',
            'waiting_on_customer' => 'En attente du client',
            'recently_updated' => 'Mis à jour récemment',
        ],
    ],

];
