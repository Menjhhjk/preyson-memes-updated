<ul class="registration-requirements {{ $extraClass ?? '' }}" id="{{ $rulesId }}">
    @foreach ($requirements as $key => $text)
        <li data-rule="{{ $key }}"><span class="requirement-icon" aria-hidden="true">○</span><span class="requirement-text">{{ $text }}</span><span class="sr-only" data-rule-state>Not met</span></li>
    @endforeach
</ul>
