{{-- resources/views/admin/bank_accounts/create.blade.php --}}

@extends('layouts.admin') {{-- قم بتغيير هذا ليتناسب مع اسم ملف التخطيط (Layout) الخاص بك --}}

@section('content')
    <div class="container-fluid"> {{-- استخدم كلاسات التنسيق المناسبة لديك --}}
        <h1 class="h3 mb-4 text-gray-800">إضافة حساب بنكي جديد</h1>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="{{ route('admin.bank-accounts.store') }}" method="POST">
                    @csrf {{-- Include CSRF token --}}

                    {{-- Include the form partial --}}
                    @include('admin.bank_accounts._form')

                </form>
            </div>
        </div>
    </div>
@endsection