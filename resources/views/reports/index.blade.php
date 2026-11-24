@extends('reports.layout')
@section('title', __('reports.title'))
@section('report')
<x-page-header :title="__('reports.title')" />
@include('reports._workspace')
@endsection
