@extends('errors.layout')

@section('code', '403')

@section('title')
    You can’t open this page
@endsection

@section('message')
    {{ filled($exception?->getMessage()) && $exception->getMessage() !== 'This action is unauthorized.' ? $exception->getMessage() : 'Your account doesn’t have access to it. If you think it should, ask the church office.' }}
@endsection
