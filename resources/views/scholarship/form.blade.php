@extends('layouts.admin')

@php($isEdit = $application !== null)
@section('title', $isEdit ? 'Edit Scholarship Application' : 'New Scholarship Application')
@section('heading', $isEdit ? 'Edit Scholarship Application' : 'New Scholarship Application')
@section('subtitle', 'Student, education, Tendupatta, documents, and own-bank details')

@php($breadcrumbs = ['Applications' => route('applications.index'), $isEdit ? 'Edit' : 'Create' => null])

@section('content')
    <form method="POST" action="{{ $isEdit ? route('applications.update', $application) : route('applications.store') }}">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <x-card title="Application Details" icon="fa-regular fa-file-lines" class="mb-3">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Academic Session</label>
                    <select class="form-select" name="academic_session_id" required>
                        @foreach($sessions as $session)
                            <option value="{{ $session->id }}" @selected(old('academic_session_id', $application?->academic_session_id) == $session->id)>{{ $session->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Scheme</label>
                    <select class="form-select" name="scheme_id" required @disabled($application && ! $application->is_draft)>
                        @foreach($schemes as $scheme)
                            <option value="{{ $scheme->id }}" @selected(old('scheme_id', $application?->scheme_id) == $scheme->id)>{{ $scheme->name }}</option>
                        @endforeach
                    </select>
                    @if($application && ! $application->is_draft)
                        <input type="hidden" name="scheme_id" value="{{ $application->scheme_id }}">
                    @endif
                </div>
                <div class="col-md-4">
                    <label class="form-label">Class</label>
                    <input class="form-control" name="class" value="{{ old('class', $application?->class) }}" placeholder="10, 12, or course year">
                </div>
            </div>
        </x-card>

        <x-card title="Student Details" icon="fa-solid fa-user-graduate" class="mb-3">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Student Aadhaar</label>
                    <input class="form-control" name="student_aadhaar" value="{{ old('student_aadhaar', $application?->student_aadhaar) }}" required maxlength="12">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Student Name</label>
                    <input class="form-control" name="student_name" value="{{ old('student_name', $application?->student_name) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mobile</label>
                    <input class="form-control" name="mobile" value="{{ old('mobile', $application?->mobile) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Gender</label>
                    <input class="form-control" name="gender" value="{{ old('gender', $application?->gender) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date of Birth</label>
                    <input class="form-control" type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($application?->date_of_birth)->format('Y-m-d')) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Address</label>
                    <input class="form-control" name="address" value="{{ old('address', $application?->address) }}">
                </div>
            </div>
        </x-card>

        <x-card title="Education and Tendupatta" icon="fa-solid fa-school" class="mb-3">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">School / College</label><input class="form-control" name="school_college_name" value="{{ old('school_college_name', $application?->school_college_name) }}"></div>
                <div class="col-md-4"><label class="form-label">Board / University</label><input class="form-control" name="board_university" value="{{ old('board_university', $application?->board_university) }}"></div>
                <div class="col-md-4"><label class="form-label">Roll Number</label><input class="form-control" name="roll_number" value="{{ old('roll_number', $application?->roll_number) }}"></div>
                <div class="col-md-3"><label class="form-label">Marks Obtained</label><input class="form-control" name="marks_obtained" value="{{ old('marks_obtained', $application?->marks_obtained) }}"></div>
                <div class="col-md-3"><label class="form-label">Maximum Marks</label><input class="form-control" name="maximum_marks" value="{{ old('maximum_marks', $application?->maximum_marks) }}"></div>
                <div class="col-md-3"><label class="form-label">Current Year</label><input class="form-control" name="current_year_of_study" value="{{ old('current_year_of_study', $application?->current_year_of_study) }}"></div>
                <div class="col-md-3"><label class="form-label">Sangrahak Card</label><input class="form-control" name="sangrahak_card_number" value="{{ old('sangrahak_card_number', $application?->sangrahak_card_number) }}"></div>
                <div class="col-md-4"><label class="form-label">Collection Year</label><input class="form-control" name="tendupatta_collections[0][collection_year]" value="{{ old('tendupatta_collections.0.collection_year', $application?->tendupattaCollections?->first()?->collection_year) }}"></div>
                <div class="col-md-4"><label class="form-label">Quantity Gaddi</label><input class="form-control" name="tendupatta_collections[0][quantity_gaddi]" value="{{ old('tendupatta_collections.0.quantity_gaddi', $application?->tendupattaCollections?->first()?->quantity_gaddi) }}"></div>
            </div>
        </x-card>

        <x-card title="Head of Family and Student Bank" icon="fa-solid fa-building-columns" class="mb-3">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Head of Family Aadhaar</label><input class="form-control" name="head_of_family_aadhaar" value="{{ old('head_of_family_aadhaar', $application?->head_of_family_aadhaar) }}" maxlength="12"></div>
                <div class="col-md-4"><label class="form-label">Head of Family Name</label><input class="form-control" name="head_of_family_name" value="{{ old('head_of_family_name', $application?->head_of_family_name) }}"></div>
                <div class="col-md-4"><label class="form-label">Father / Husband Name</label><input class="form-control" name="head_of_family_father_or_husband_name" value="{{ old('head_of_family_father_or_husband_name', $application?->head_of_family_father_or_husband_name) }}"></div>
                <div class="col-md-3"><label class="form-label">Student Account Number</label><input class="form-control" name="student_bank_account_number" value="{{ old('student_bank_account_number', $application?->student_bank_account_number) }}"></div>
                <div class="col-md-3"><label class="form-label">IFSC</label><input class="form-control" name="student_bank_ifsc" value="{{ old('student_bank_ifsc', $application?->student_bank_ifsc) }}"></div>
                <div class="col-md-3"><label class="form-label">Bank Name</label><input class="form-control" name="student_bank_name" value="{{ old('student_bank_name', $application?->student_bank_name) }}"></div>
                <div class="col-md-3"><label class="form-label">Account Holder</label><input class="form-control" name="student_bank_account_holder_name" value="{{ old('student_bank_account_holder_name', $application?->student_bank_account_holder_name) }}"></div>
            </div>
        </x-card>

        <div class="d-flex justify-content-end gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('applications.index') }}">Cancel</a>
            @if(! $application || $application->is_draft)
                <button class="btn btn-outline-primary" name="intent" value="draft" type="submit"><i class="fa-regular fa-floppy-disk me-1"></i>Save Draft</button>
                <button class="btn btn-primary" name="intent" value="submit" type="submit"><i class="fa-solid fa-paper-plane me-1"></i>Submit</button>
            @else
                <button class="btn btn-primary" name="intent" value="resubmit" type="submit"><i class="fa-solid fa-rotate me-1"></i>Resubmit</button>
            @endif
        </div>
    </form>
@endsection
