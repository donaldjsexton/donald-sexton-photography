@extends('errors::minimal')

@section('title', __('Upload Too Large'))
@section('code', '413')
@section('message', __('That upload is larger than the server accepts in a single request. Go back and try again with fewer or smaller files.'))
