@extends('layouts.admin')

@section('title', 'إضافة خدمة إضافية جديدة')

@section('content')
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">إضافة خدمة إضافية جديدة</h1>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form action="{{ route('admin.add-on-services.store') }}" method="POST">
                @include('admin.add-on-services._form')
            </form>
        </div>
    </div>
@endsection
