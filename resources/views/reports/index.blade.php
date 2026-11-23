@extends('layouts.app')
@section('title', __('reports.title'))
@section('content')
<x-page-header :title="__('reports.title')" :subtitle="__('reports.subtitle')" />
@include('reports._workspace')
<section class="cardx card-pad">
    <div class="section-head"><div><div class="section-title">گزارش مورد نظر را انتخاب کنید</div><div class="card-sub">فیلترها، جدول نتایج و نسخه چاپی در همان فضای کاری نمایش داده می‌شود.</div></div></div>
    <div class="empty compact"><i class="bi bi-bar-chart"></i><div class="empty-title">از زبانه‌های بالا یک گزارش را باز کنید.</div></div>
</section>
@endsection
