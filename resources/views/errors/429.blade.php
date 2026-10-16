@extends('errors.layout')
@section('code', '429')
@section('heading', 'Too many requests')
@section('message', $exception?->getMessage() && in_array(429, [403]) && $exception->getMessage() !== 'This action is unauthorized.' ? $exception->getMessage() : 'Please wait a moment before trying again.')
