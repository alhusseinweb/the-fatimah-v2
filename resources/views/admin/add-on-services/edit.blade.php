@extends('layouts.admin')

@section('title', 'تعديل الخدمة الإضافية: ' . $addOnService->name_ar)

@section('content')
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">تعديل الخدمة الإضافية: {{ $addOnService->name_ar }}</h1>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form action="{{ route('admin.add-on-services.update', $addOnService->id) }}" method="POST">
                @method('PUT')
                @include('admin.add-on-services._form', ['addOnService' => $addOnService])
            </form>
        </div>
    </div>
@endsection
