@extends('errors.layout')

@section('title', 'Too many requests')
@section('status', '429')
@section('heading', 'Please slow down')
@section('message', 'Too many requests were received. Wait a moment, then try again.')
