@php($multiple = $multiple ?? false)
<input type="file" id="{{ $inputId }}" name="{{ $multiple ? 'media[]' : 'media' }}"
    @if($multiple) multiple @endif @required($required ?? false)
    accept="{{ \App\Support\PostMedia::accept() }}{{ ($allowZip ?? false) ? ',.zip' : '' }}"
    data-media-input data-max-upload-bytes="{{ \App\Support\PostMedia::MAX_UPLOAD_BYTES }}"
    data-max-files="{{ $multiple ? 6 : 1 }}" aria-describedby="{{ $inputId }}-limit {{ $inputId }}-status">
<strong class="upload-limit" id="{{ $inputId }}-limit">Maximum: 100 MB per file{{ $multiple ? ' and 100 MB total per upload (up to 6 files)' : '' }}.</strong>
<span class="upload-status" id="{{ $inputId }}-status" data-upload-status role="status" aria-live="polite">No file selected.</span>
