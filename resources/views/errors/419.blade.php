@extends('errors.layout')
@section('code', '419')
@section('heading', 'Session expired')
@section('message', $exception?->getMessage() && in_array(419, [403]) && $exception->getMessage() !== 'This action is unauthorized.' ? $exception->getMessage() : 'Your session expired for security reasons. Go back, refresh the page and try again.')
