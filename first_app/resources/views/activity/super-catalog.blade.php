@extends('layouts.site')
@section('title', 'Super-reaction library')
@section('content')
<div class="page-heading"><div><span class="eyebrow">MAKE THE BIG REACTIONS YOURS</span><h1>Super-reaction library</h1><p>Replace the geometric demo GIFs and sounds whenever your own assets are ready.</p></div></div>
<section class="panel"><h2>Add a Super-reaction</h2><form method="POST" action="{{ route('super-catalog.store') }}" enctype="multipart/form-data">@csrf @include('activity.super-fields', ['type' => null])<button class="button" type="submit">Add Super-reaction</button></form></section>
<div class="catalog-grid">@foreach($types as $type)<article class="panel catalog-card"><img class="catalog-preview" src="{{ $type->effect()['gif'] }}" alt="{{ $type->name }}"><audio controls preload="none" src="{{ $type->effect()['sound'] }}"></audio><form method="POST" action="{{ route('super-catalog.update', $type) }}" enctype="multipart/form-data">@csrf @method('PUT')@include('activity.super-fields')<button class="button secondary" type="submit">Save changes</button></form></article>@endforeach</div>
@include('partials.pagination', ['items' => $types])
@endsection
