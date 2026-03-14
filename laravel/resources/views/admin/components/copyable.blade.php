<div class="input-group">
    <input type="text" class="form-control" value="{{ $value }}" readonly id="{{ $uniqueId }}">
    <span class="input-group-btn">
        <button class="btn btn-default" type="button" onclick="copyToClipboard('{{ $uniqueId }}', this, '{{ $successText }}')">
            <i class="fa fa-copy"></i> {{ $buttonText }}
        </button>
    </span>
</div>

<script>
function copyToClipboard(inputId, btn, successText) {
    var input = document.getElementById(inputId);
    input.select();
    document.execCommand('copy');

    var originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-check"></i> ' + successText;
    btn.classList.add('btn-success');
    btn.classList.remove('btn-default');

    setTimeout(function() {
        btn.innerHTML = originalText;
        btn.classList.remove('btn-success');
        btn.classList.add('btn-default');
    }, 2000);
}
</script>