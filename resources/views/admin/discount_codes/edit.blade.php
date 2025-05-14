{{-- resources/views/admin/discount_codes/edit.blade.php --}}

@extends('layouts.admin') {{-- قم بتغيير هذا ليتناسب مع اسم ملف التخطيط (Layout) الخاص بك --}}

@section('content')
    <div class="container-fluid">
        <h1 class="h3 mb-4 text-gray-800">تعديل كود الخصم</h1>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="{{ route('admin.discount-codes.update', $discountCode) }}" method="POST">
                    @csrf
                    @method('PUT')

                    {{-- Include the form partial, passing the existing $discountCode and $types --}}
                    @include('admin.discount_codes._form', ['discountCode' => $discountCode, 'types' => $types])

                </form>
            </div>
        </div>
    </div>
@endsection