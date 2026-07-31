<?php

use Illuminate\Support\Facades\Route;
use Modules\AgentConnector\Http\Controllers\Api\AgentConnectorApiController;

Route::prefix('api/agent-connector')->middleware(['api', 'auth'])->group(function () {
    Route::post('/chat', [AgentConnectorApiController::class, 'chat']);
    Route::get('/memories', [AgentConnectorApiController::class, 'memories']);
    Route::get('/tools', [AgentConnectorApiController::class, 'tools']);
    Route::post('/tools/sync', [AgentConnectorApiController::class, 'syncTools']);
});
