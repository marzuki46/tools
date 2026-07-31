<?php

use Illuminate\Support\Facades\Route;
use Modules\AgentConnector\Http\Controllers\AgentConnectorController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/agent-connector', [AgentConnectorController::class, 'index'])
        ->name('agentconnector.index');

    Route::post('/agent-connector/chat', [AgentConnectorController::class, 'chat'])
        ->name('agentconnector.chat');

    Route::get('/agent-connector/chat/history', [AgentConnectorController::class, 'history'])
        ->name('agentconnector.history');

    Route::get('/agent-connector/chat/{id}/status', [AgentConnectorController::class, 'messageStatus'])
        ->name('agentconnector.status');

    Route::post('/agent-connector/chat/clear', [AgentConnectorController::class, 'clearHistory'])
        ->name('agentconnector.clear');

    Route::get('/content-analyzer', [AgentConnectorController::class, 'analyzer'])
        ->name('agentconnector.analyzer');

    Route::post('/content-analyzer/analyze', [AgentConnectorController::class, 'analyze'])
        ->name('agentconnector.analyze');

    Route::get('/content-analyzer/wp-posts', [AgentConnectorController::class, 'wpPosts'])
        ->name('agentconnector.wp-posts');

    Route::post('/content-analyzer/analyze-post', [AgentConnectorController::class, 'analyzePost'])
        ->name('agentconnector.analyze-post');

    Route::post('/content-analyzer/schedule-optimization', [AgentConnectorController::class, 'scheduleOptimization'])
        ->name('agentconnector.schedule-optimization');

    Route::get('/content-analyzer/reports', [AgentConnectorController::class, 'reports'])
        ->name('agentconnector.reports');
});
