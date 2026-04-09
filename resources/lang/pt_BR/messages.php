<?php

return [

    'ticket' => [
        'reply_sent' => 'Resposta enviada.',
        'note_added' => 'Nota adicionada.',
        'assigned' => 'Ticket atribuído.',
        'status_updated' => 'Status atualizado.',
        'priority_updated' => 'Prioridade atualizada.',
        'tags_updated' => 'Tags atualizadas.',
        'department_updated' => 'Departamento atualizado.',
        'macro_applied' => 'Macro ":name" aplicada.',
        'following' => 'Seguindo o ticket.',
        'unfollowed' => 'Deixou de seguir o ticket.',
        'only_internal_notes_pinned' => 'Apenas notas internas podem ser fixadas.',
        'updated' => 'Ticket atualizado.',
        'created' => 'Ticket criado com sucesso.',
        'closed' => 'Ticket fechado.',
        'reopened' => 'Ticket reaberto.',
        'customers_cannot_close' => 'Clientes não podem fechar tickets.',
    ],

    'guest' => [
        'created' => 'Ticket criado. Salve este link para verificar o status do seu ticket.',
        'ticket_closed' => 'Este ticket está fechado.',
    ],

    'bulk' => [
        'updated' => ':count ticket(s) atualizado(s).',
    ],

    'rating' => [
        'only_resolved_closed' => 'Você só pode avaliar tickets resolvidos ou fechados.',
        'already_rated' => 'Este ticket já foi avaliado.',
        'thanks' => 'Obrigado pelo seu feedback!',
    ],

    'canned_response' => [
        'created' => 'Resposta predefinida criada.',
        'updated' => 'Resposta predefinida atualizada.',
        'deleted' => 'Resposta predefinida excluída.',
    ],

    'department' => [
        'created' => 'Departamento criado.',
        'updated' => 'Departamento atualizado.',
        'deleted' => 'Departamento excluído.',
    ],

    'tag' => [
        'created' => 'Tag criada.',
        'updated' => 'Tag atualizada.',
        'deleted' => 'Tag excluída.',
    ],

    'macro' => [
        'created' => 'Macro criada.',
        'updated' => 'Macro atualizada.',
        'deleted' => 'Macro excluída.',
    ],

    'escalation_rule' => [
        'created' => 'Regra criada.',
        'updated' => 'Regra atualizada.',
        'deleted' => 'Regra excluída.',
    ],

    'sla_policy' => [
        'created' => 'Política de SLA criada.',
        'updated' => 'Política de SLA atualizada.',
        'deleted' => 'Política de SLA excluída.',
    ],

    'settings' => [
        'updated' => 'Configurações atualizadas.',
    ],

    'plugin' => [
        'uploaded' => 'Plugin enviado com sucesso. Agora você pode ativá-lo.',
        'upload_failed' => 'Falha ao enviar o plugin: :error',
        'activated' => 'Plugin ativado com sucesso.',
        'activate_failed' => 'Falha ao ativar o plugin: :error',
        'deactivated' => 'Plugin desativado com sucesso.',
        'deactivate_failed' => 'Falha ao desativar o plugin: :error',
        'composer_delete' => 'Plugins do Composer não podem ser excluídos. Remova o pacote pelo Composer.',
        'deleted' => 'Plugin excluído com sucesso.',
        'delete_failed' => 'Falha ao excluir o plugin: :error',
    ],

    'middleware' => [
        'not_admin' => 'Você não está autorizado como administrador de suporte.',
        'not_agent' => 'Você não está autorizado como agente de suporte.',
    ],

    'inbound_email' => [
        'disabled' => 'O e-mail de entrada está desativado.',
        'unknown_adapter' => 'Adaptador desconhecido.',
        'invalid_signature' => 'Assinatura inválida.',
        'processing_failed' => 'O processamento falhou.',
    ],

];
