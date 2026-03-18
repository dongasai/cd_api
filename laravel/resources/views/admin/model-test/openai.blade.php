<style>
    .openai-test-container {
        height: calc(100vh - 290px);
        min-height: 600px;
    }

    .openai-test-container iframe {
        width: 100%;
        height: 100%;
        border: none;
        display: block;
    }
</style>

<div class="openai-test-container">
    <iframe
        src="{{ asset('chat.html') }}?apiBase={{ urlencode(config('app.url') . '/api/openai/v1') }}&apiKey={{ urlencode($defaultApiKey ?? '') }}&autoLoadModels=1"
        id="chat-iframe"></iframe>
</div>