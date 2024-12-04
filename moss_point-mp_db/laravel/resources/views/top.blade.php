@extends('layouts.app')

@section('title', 'トップページ')

@section('content')
@foreach ($csv_dir_list as $key => $dir_data)                                                                                                                          @foreach ($dir_data as $dir)
<div class="btn-group">
  <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
    [{{$key}}]{{$dir}}
  </button>
  <ul class="dropdown-menu">
    @for ($i=1; $i <= 12; $i++)
    <li><a class="dropdown-item" href="/race_card/{{$key}}/{{$dir}}/{{$i}}">{{$i}}</a></li>
    @endfor
  </ul>
</div>
@endforeach
<hr />
@endforeach
@endsection