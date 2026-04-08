<?php

return [

    'status' => [
        'open' => 'Ouvert',
        'in_progress' => 'En cours',
        'waiting_on_customer' => 'En attente du client',
        'waiting_on_agent' => "En attente de l'agent",
        'escalated' => 'Escaladé',
        'resolved' => 'Résolu',
        'closed' => 'Fermé',
        'snoozed' => 'Mis en veille',
        'unsnoozed' => 'Réactivé',
        'reopened' => 'Réouvert',
        'live' => 'En direct',
    ],

    'priority' => [
        'low' => 'Bas',
        'medium' => 'Moyen',
        'high' => 'Élevé',
        'urgent' => 'Urgent',
        'critical' => 'Critique',
    ],

    'activity_type' => [
        'status_changed' => 'Statut modifié',
        'assigned' => 'Assigné',
        'unassigned' => 'Désassigné',
        'priority_changed' => 'Priorité modifiée',
        'tag_added' => 'Étiquette ajoutée',
        'tag_removed' => 'Étiquette supprimée',
        'escalated' => 'Escaladé',
        'sla_breached' => 'SLA non respecté',
        'replied' => 'Répondu',
        'note_added' => 'Note ajoutée',
        'department_changed' => 'Département modifié',
        'reopened' => 'Réouvert',
        'resolved' => 'Résolu',
        'closed' => 'Fermé',
        'snoozed' => 'Mis en veille',
        'unsnoozed' => 'Réactivé',
        'ticket_split' => 'Ticket scindé',
    ],

];
