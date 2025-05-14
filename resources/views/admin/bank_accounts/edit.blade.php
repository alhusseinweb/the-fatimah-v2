{{-- resources/views/admin/bank_accounts/edit.blade.php --}}

@extends('layouts.admin') {{-- قم بتغيير هذا ليتناسب مع اسم ملف التخطيط (Layout) الخاص بك --}}

@section('content')
    <div class="container-fluid"> {{-- استخدم كلاسات التنسيق المناسبة لديك --}}
        <h1 class="h3 mb-4 text-gray-800">تعديل الحساب البنكي</h1>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="{{ route('admin.bank-accounts.update', $bankAccount) }}" method="POST">
                    @csrf {{-- Include CSRF token --}}
                    @method('PUT') {{-- Specify the PUT method for updates --}}

                    {{-- Include the form partial, passing the existing $bankAccount --}}
                    @include('admin.bank_accounts._form', ['bankAccount' => $bankAccount])

                </form>
            </div>
        </div>
    </div>
@endsection