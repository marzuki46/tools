<?php

use Illuminate\Support\Facades\Route;
use Modules\AgentConnector\Http\Controllers\AgentConnectorController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/agent-connector', [AgentConnectorController::class, 'index'])
        ->name('agentconnector.index');

    Route::post('/agent-connector/chat', [AgentConnectorController::class, 'chat'])
        ->name('agentconnector.chat');

    Route::get('/content-analyzer', [AgentConnectorController::class, 'analyzer'])
        ->name('agentconnector.analyzer');

    Route::post('/content-analyzer/analyze', [AgentConnectorController::class, 'analyze'])
        ->name('agentconnector.analyze');
});
