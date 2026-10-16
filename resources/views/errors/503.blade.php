@extends('errors.layout')
@section('code', '503')
@section('heading', 'Under maintenance')
@section('message', $exception?->getMessage() && in_array(503, [403]) && $exception->getMessage() !== 'This action is unauthorized.' ? $exception->getMessage() : 'The system is temporarily unavailable for maintenance. Follow the manual continuity procedure until service is restored.')
