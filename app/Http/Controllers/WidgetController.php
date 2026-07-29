<?php

namespace App\Http\Controllers;

use App\Models\Websites\WebsiteTool;
use Illuminate\Http\Request;

class WidgetController extends Controller
{
    public function js(Request $request, string $toolSlug, string $apiKey)
    {
        $websiteTool = WebsiteTool::authenticate($apiKey);

        if (!$websiteTool) {
            return response("console.error('Juki Tools: Invalid or expired API key.');", 200, [
                'Content-Type' => 'application/javascript',
            ]);
        }

        $tool = $websiteTool->tool;
        if ($tool->slug !== $toolSlug) {
            return response("console.error('Juki Tools: Tool mismatch.');", 200, [
                'Content-Type' => 'application/javascript',
            ]);
        }

        $websiteTool->touchLastUsed($request->ip());

        $config = $websiteTool->config ?? [];

        $script = <<<JS
(function() {
    if (window.__jukiToolsLoaded) return;
    window.__jukiToolsLoaded = true;

    var container = document.createElement('div');
    container.id = 'juki-tools-widget';
    container.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:999999;';

    var btn = document.createElement('button');
    btn.id = 'juki-tools-toggle';
    btn.innerHTML = '⚡ Tools';
    btn.style.cssText = 'background:#4f46e5;color:#fff;border:none;border-radius:50px;padding:12px 24px;font-size:14px;font-weight:600;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,0.15);';

    var iframe = document.createElement('iframe');
    iframe.src = '{$request->getSchemeAndHttpHost()}/widget/{$toolSlug}/{$apiKey}/frame';
    iframe.style.cssText = 'display:none;position:fixed;bottom:80px;right:20px;width:400px;height:600px;border:none;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.2);background:#fff;';
    iframe.allow = 'clipboard-write';

    btn.onclick = function() {
        var isHidden = iframe.style.display === 'none';
        iframe.style.display = isHidden ? 'block' : 'none';
        btn.innerHTML = isHidden ? '✕ Close' : '⚡ Tools';
    };

    container.appendChild(btn);
    iframe.id = 'juki-tools-iframe';
    document.body.appendChild(iframe);
    document.body.appendChild(container);
})();
JS;

        return response($script, 200, [
            'Content-Type' => 'application/javascript',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    public function frame(Request $request, string $toolSlug, string $apiKey)
    {
        $websiteTool = WebsiteTool::authenticate($apiKey);

        if (!$websiteTool) {
            return response('<h2 style="color:red;font-family:sans-serif;">Invalid API Key</h2>', 200);
        }

        $tool = $websiteTool->tool;
        if ($tool->slug !== $toolSlug) {
            return response('<h2 style="color:red;font-family:sans-serif;">Tool Mismatch</h2>', 200);
        }

        $websiteTool->touchLastUsed($request->ip());

        if ($toolSlug === 'meta-ads-generator') {
            return redirect()->route('metaadsimagegenerator.create', ['embed' => $apiKey]);
        }

        return response('<h2 style="font-family:sans-serif;">' . e($tool->name) . '</h2><p>Embed ready.</p>', 200);
    }

    public function snippet(Request $request, string $toolSlug, string $apiKey)
    {
        $websiteTool = WebsiteTool::authenticate($apiKey);

        if (!$websiteTool) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        $tool = $websiteTool->tool;
        if ($tool->slug !== $toolSlug) {
            return response()->json(['error' => 'Tool mismatch'], 400);
        }

        $url = $request->getSchemeAndHttpHost();

        $html = <<<HTML
<!-- Juki Tools Widget -->
<script src="{$url}/widget/{$toolSlug}/{$apiKey}.js"></script>
HTML;

        return response()->json([
            'snippet' => $html,
            'tool' => $tool->name,
            'domain' => $websiteTool->website->domain,
        ]);
    }
}
