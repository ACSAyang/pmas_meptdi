@extends('errors.layout')
@section('code', '403')
@section('heading', 'Access not authorized')
@section('message', $exception?->getMessage() && in_array(403, [403]) && $exception->getMessage() !== 'This action is unauthorized.' ? $exception->getMessage() : 'You do not have permission to view this page or perform this action. If you believe this is an error, contact the System Administrator.')
