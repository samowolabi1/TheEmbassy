@extends('errors.layout')

@section('code', '429')

@section('title')
    Slow down a little
@endsection

@section('message')
    That was a lot of requests in a short time. Wait a minute, then try again.
@endsection
