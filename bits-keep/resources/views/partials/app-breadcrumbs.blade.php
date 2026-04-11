@php
    $items = $items ?? [];
    $class = $class ?? 'mb-4';
@endphp

<nav class="breadcrumb {{ $class }}">
  @include('partials.brand-home-link')
  @foreach ($items as $item)
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    @if (!empty($item['url']) && empty($item['current']))
      <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
    @else
      <span @class(['current' => !empty($item['current'])])>{{ $item['label'] }}</span>
    @endif
  @endforeach
</nav>
