@extends('errors.layout')
@section('code', '404')
@section('heading', 'Page not found')
@section('message', $exception?->getMessage() && in_array(404, [403]) && $exception->getMessage() !== 'This action is unauthorized.' ? $exception->getMessage() : 'The record or page you requested does not exist or has been moved.')
