@extends('errors.layout')
@section('code', '500')
@section('heading', 'System error')
@section('message', $exception?->getMessage() && in_array(500, [403]) && $exception->getMessage() !== 'This action is unauthorized.' ? $exception->getMessage() : 'Something went wrong on our side. The error has been logged. Please try again or contact the System Administrator.')
