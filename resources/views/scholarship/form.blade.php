@extends('layouts.admin')

@php($isEdit = $application !== null)
@php($selectedScheme = (int) old('scheme_id', $application?->scheme_id ?: ($schemes->first()?->id ?? 0)))
@php($isCourseScheme = in_array($selectedScheme, [3, 4], true))
@php($documents = $application?->documents?->keyBy('document_type') ?? collect())

@section('title', $isEdit ? 'Edit Scholarship Application' : 'New Scholarship Application')
@section('heading', $isEdit ? 'Edit Scholarship Application' : 'New Scholarship Application')
@section('subtitle', 'VLE scholarship application entry')

@php($breadcrumbs = ['Applications' => route('applications.index'), $isEdit ? 'Edit' : 'Create' => null])

@section('content')
    <form method="POST" action="{{ $isEdit ? route('applications.update', $application) : route('applications.store') }}" enctype="multipart/form-data" id="scholarship-form">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <x-card title="Documents Required (Attach Scanned Copy)" icon="fa-regular fa-file-lines" class="mb-3">
            <ul class="mb-0">
                <li>Sangrahak Card / संग्राहक कार्ड</li>
                <li>Aadhaar Card of Student / छात्र का आधार कार्ड</li>
                <li>Head of Family Aadhaar Card / परिवार के मुखिया का आधार कार्ड</li>
                <li>Attested Marksheet of passing Class / उत्तीर्ण कक्षा की सत्यापित मार्कशीट</li>
                <li>Student Bank Passbook / छात्र बैंक पासबुक</li>
                @if($isCourseScheme)
                    <li>Admission Receipt / प्रवेश रसीद</li>
                @endif
            </ul>
        </x-card>

        <x-card title="Information Regarding Primary Society / प्राथमिक सोसायटी के संबंध में जानकारी" icon="fa-solid fa-building" class="mb-3">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Academic Session / शिक्षा सत्र <span class="text-danger">*</span></label>
                    <select class="form-select" name="academic_session_id" required>
                        @foreach($sessions as $session)
                            <option value="{{ $session->id }}" @selected(old('academic_session_id', $application?->academic_session_id) == $session->id)>{{ $session->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Scheme / योजना <span class="text-danger">*</span></label>
                    <select class="form-select" name="scheme_id" id="scheme_id" required @disabled($application && ! $application->is_draft)>
                        @foreach($schemes as $scheme)
                            <option value="{{ $scheme->id }}" @selected(old('scheme_id', $application?->scheme_id) == $scheme->id)>{{ $scheme->name }}</option>
                        @endforeach
                    </select>
                    @if($application && ! $application->is_draft)
                        <input type="hidden" name="scheme_id" value="{{ $application->scheme_id }}">
                    @endif
                </div>
                <div class="col-md-4 course-field">
                    <label class="form-label">Education Year / शिक्षा वर्ष <span class="text-danger">*</span></label>
                    <select class="form-select" name="current_year_of_study" id="current_year_of_study">
                        <option value="">Select</option>
                        @foreach([1 => 'First Year', 2 => 'Second Year', 3 => 'Third Year', 4 => 'Final Year'] as $year => $label)
                            <option value="{{ $year }}" @selected(old('current_year_of_study', $application?->current_year_of_study) == $year)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">District Union / जिला संघ <span class="text-danger">*</span></label>
                    <select class="form-select" name="district_union_id" id="district_union_id" required data-selected="{{ old('district_union_id', $application?->district_union_id) }}">
                        <option value="">--CHOOSE--</option>
                        @foreach($districtUnions as $union)
                            <option value="{{ $union->id }}" @selected(old('district_union_id', $application?->district_union_id) == $union->id)>{{ $union->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Samiti Name / समिति का नाम <span class="text-danger">*</span></label>
                    <select class="form-select" name="samiti_id" id="samiti_id" required data-selected="{{ old('samiti_id', $application?->samiti_id) }}">
                        <option value="">--CHOOSE--</option>
                        @foreach($samitis as $samiti)
                            <option value="{{ $samiti->id }}" @selected(old('samiti_id', $application?->samiti_id) == $samiti->id)>{{ $samiti->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">PHAD Name / फड़ का नाम <span class="text-danger">*</span></label>
                    <select class="form-select" name="phad_id" id="phad_id" required data-selected="{{ old('phad_id', $application?->phad_id) }}">
                        <option value="">--CHOOSE--</option>
                        @foreach($phads as $phad)
                            <option value="{{ $phad->id }}" @selected(old('phad_id', $application?->phad_id) == $phad->id)>{{ $phad->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">District / ज़िला <span class="text-danger">*</span></label>
                    <select class="form-select" name="district_id" id="district_id" required data-selected="{{ old('district_id', $application?->district_id) }}">
                        <option value="">--CHOOSE--</option>
                        @foreach($districts as $district)
                            <option value="{{ $district['id'] }}" @selected(old('district_id', $application?->district_id) == $district['id'])>{{ $district['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Block / ब्लॉक <span class="text-danger">*</span></label>
                    <select class="form-select" name="block_code" id="block_code" required data-selected="{{ old('block_code', $application?->block_code) }}">
                        <option value="">--CHOOSE--</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Area / क्षेत्र <span class="text-danger">*</span></label>
                    <select class="form-select" name="area" id="area" required>
                        <option value="">Select Area</option>
                        <option value="Rural" @selected(old('area', $application?->area) === 'Rural')>Rural</option>
                        <option value="Urban" @selected(old('area', $application?->area) === 'Urban')>Urban</option>
                    </select>
                </div>

                <div class="col-md-4 area-rural">
                    <label class="form-label">Gram Panchayat / ग्राम पंचायत <span class="text-danger">*</span></label>
                    <select class="form-select" name="gram_panchayat_code" id="gram_panchayat_code" data-selected="{{ old('gram_panchayat_code', $application?->gram_panchayat_code) }}">
                        <option value="">--CHOOSE--</option>
                    </select>
                </div>
                <div class="col-md-4 area-rural">
                    <label class="form-label">Village / ग्राम <span class="text-danger">*</span></label>
                    <select class="form-select" name="village_code" id="village_code" data-selected="{{ old('village_code', $application?->village_code) }}">
                        <option value="">--CHOOSE--</option>
                    </select>
                </div>
                <div class="col-md-4 area-urban">
                    <label class="form-label">City / शहर <span class="text-danger">*</span></label>
                    <select class="form-select" name="city_code" id="city_code" data-selected="{{ old('city_code', $application?->city_code) }}">
                        <option value="">--CHOOSE--</option>
                    </select>
                </div>
                <div class="col-md-4 area-urban">
                    <label class="form-label">Ward / वार्ड <span class="text-danger">*</span></label>
                    <select class="form-select" name="ward_code" id="ward_code" data-selected="{{ old('ward_code', $application?->ward_code) }}">
                        <option value="">--CHOOSE--</option>
                    </select>
                </div>
                <div class="col-md-4 area-urban">
                    <label class="form-label">Ward Number / वार्ड संख्या</label>
                    <input class="form-control" name="ward_number" id="ward_number" value="{{ old('ward_number', $application?->ward_number) }}" readonly>
                </div>
            </div>
        </x-card>

        <x-card title="Head of Family Detail / परिवार मुखिया का विवरण" icon="fa-solid fa-people-roof" class="mb-3">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Sangrahak Card Number / संग्राहक कार्ड नंबर <span class="text-danger">*</span></label><input class="form-control" name="sangrahak_card_number" value="{{ old('sangrahak_card_number', $application?->sangrahak_card_number) }}" required></div>
                <div class="col-md-4"><label class="form-label">Head of Family Name / परिवार मुखिया का नाम <span class="text-danger">*</span></label><input class="form-control only-chars" name="head_of_family_name" value="{{ old('head_of_family_name', $application?->head_of_family_name) }}" required></div>
                <div class="col-md-4"><label class="form-label">Head of Family Aadhaar / मुखिया आधार <span class="text-danger">*</span></label><input class="form-control only-numbers aadhaar-field" name="head_of_family_aadhaar" id="head_of_family_aadhaar" value="{{ old('head_of_family_aadhaar', $application?->head_of_family_aadhaar) }}" minlength="12" maxlength="12" required><div class="small text-danger"></div></div>
                <div class="col-md-4"><label class="form-label">Father / Husband Name</label><input class="form-control only-chars" name="head_of_family_father_or_husband_name" value="{{ old('head_of_family_father_or_husband_name', $application?->head_of_family_father_or_husband_name) }}"></div>
                <div class="col-md-4"><label class="form-label">Gender</label><select class="form-select" name="head_of_family_gender"><option value="">Select</option><option value="Male" @selected(old('head_of_family_gender', $application?->head_of_family_gender) === 'Male')>Male</option><option value="Female" @selected(old('head_of_family_gender', $application?->head_of_family_gender) === 'Female')>Female</option></select></div>
                <div class="col-md-4"><label class="form-label">Date of Birth</label><input class="form-control" type="date" name="head_of_family_date_of_birth" value="{{ old('head_of_family_date_of_birth', optional($application?->head_of_family_date_of_birth)->format('Y-m-d')) }}"></div>
            </div>
        </x-card>

        <x-card title="Information of Student / छात्र की जानकारी" icon="fa-solid fa-user-graduate" class="mb-3">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Name of Student / छात्र का नाम <span class="text-danger">*</span></label><input class="form-control only-chars" name="student_name" id="student_name" value="{{ old('student_name', $application?->student_name) }}" required></div>
                <div class="col-md-4"><label class="form-label">Gender / लिंग <span class="text-danger">*</span></label><select class="form-select" name="gender" required><option value="">Select Gender</option><option value="Male" @selected(old('gender', $application?->gender) === 'Male')>Male</option><option value="Female" @selected(old('gender', $application?->gender) === 'Female')>Female</option></select></div>
                <div class="col-md-4"><label class="form-label">Student Date of Birth / जन्मतिथि <span class="text-danger">*</span></label><input class="form-control" type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($application?->date_of_birth)->format('Y-m-d')) }}" max="{{ now()->subYears(12)->toDateString() }}" required></div>
                <div class="col-md-4"><label class="form-label">Student Aadhaar / छात्र आधार <span class="text-danger">*</span></label><input class="form-control only-numbers aadhaar-field" name="student_aadhaar" id="student_aadhaar" value="{{ old('student_aadhaar', $application?->student_aadhaar) }}" minlength="12" maxlength="12" required><div class="small text-danger"></div></div>
                <div class="col-md-4"><label class="form-label">Contact Number / संपर्क नंबर <span class="text-danger">*</span></label><input class="form-control only-numbers" name="mobile" value="{{ old('mobile', $application?->mobile) }}" minlength="10" maxlength="10" required></div>
                <div class="col-md-4"><label class="form-label">Pin Code / पिन कोड <span class="text-danger">*</span></label><input class="form-control only-numbers" name="pincode" id="pincode" value="{{ old('pincode', $application?->pincode) }}" minlength="6" maxlength="6" required><div class="small text-danger"></div></div>
                <div class="col-12"><label class="form-label">Address / पता <span class="text-danger">*</span></label><textarea class="form-control" name="address" rows="2" required>{{ old('address', $application?->address) }}</textarea></div>
            </div>
        </x-card>

        <x-card title="Student Educational Detail / छात्र शैक्षिक विवरण" icon="fa-solid fa-school" class="mb-3">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">School / College Name <span class="text-danger">*</span></label><input class="form-control" name="school_college_name" value="{{ old('school_college_name', $application?->school_college_name) }}" required></div>
                <div class="col-md-4"><label class="form-label">Passing / Admission Year</label><input class="form-control" type="number" name="admission_year" value="{{ old('admission_year', $application?->admission_year) }}" min="1900" max="2100"></div>
                <div class="col-md-4 class-field"><label class="form-label">Passing Class / उत्तीर्ण कक्षा <span class="text-danger">*</span></label><select class="form-select" name="class" id="class"><option value="">Select Class</option><option value="10" @selected(old('class', $application?->class) === '10')>10</option><option value="12" @selected(old('class', $application?->class) === '12')>12</option></select></div>
                <div class="col-md-4"><label class="form-label">Marks Obtained / प्राप्त अंक <span class="text-danger">*</span></label><input class="form-control" name="marks_obtained" id="marks_obtained" value="{{ old('marks_obtained', $application?->marks_obtained) }}" required></div>
                <div class="col-md-4"><label class="form-label">Total Marks / कुल मार्क <span class="text-danger">*</span></label><input class="form-control" name="maximum_marks" id="maximum_marks" value="{{ old('maximum_marks', $application?->maximum_marks) }}" required></div>
                <div class="col-md-4"><label class="form-label">Marks in Percentage / प्रतिशत</label><input class="form-control" id="percentage" value="{{ old('percentage', $application?->percentage) }}" readonly></div>
            </div>
        </x-card>

        <x-card title="Course Details / पाठ्यक्रम विवरण" icon="fa-solid fa-book-open" class="mb-3 course-field">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Course Name / कोर्स का नाम <span class="text-danger">*</span></label><input class="form-control" name="course_name" value="{{ old('course_name', $application?->course_name) }}"></div>
                <div class="col-md-4"><label class="form-label">Course Duration (Years) / अवधि <span class="text-danger">*</span></label><input class="form-control only-numbers" name="course_duration" value="{{ old('course_duration', $application?->course_duration) }}"></div>
                <div class="col-md-4"><label class="form-label">Institute Name / संस्थान <span class="text-danger">*</span></label><input class="form-control" name="institution_name" value="{{ old('institution_name', $application?->institution_name) }}"></div>
                <div class="col-md-4"><label class="form-label">University Name / विश्वविद्यालय <span class="text-danger">*</span></label><input class="form-control" name="board_university" value="{{ old('board_university', $application?->board_university) }}"></div>
                <div class="col-md-4"><label class="form-label">First Year Session</label><input class="form-control" name="first_year_session" value="{{ old('first_year_session', $application?->first_year_session) }}"></div>
                <div class="col-md-4"><label class="form-label">Scholarship Session</label><input class="form-control" name="scholarship_session" value="{{ old('scholarship_session', $application?->scholarship_session) }}"></div>
            </div>
        </x-card>

        <x-card title="Tendupatta Collection Details / तेंदूपत्ता संग्रह विवरण" icon="fa-solid fa-leaf" class="mb-3">
            <input type="hidden" name="tendupatta_data_source" value="MANUAL">
            <div class="row g-3">
                @for($index = 0; $index < 3; $index++)
                    @php($collection = $application?->tendupattaCollections?->values()->get($index))
                    <div class="col-md-4"><label class="form-label">Collection Year {{ $index + 1 }}</label><input class="form-control" name="tendupatta_collections[{{ $index }}][collection_year]" value="{{ old("tendupatta_collections.$index.collection_year", $collection?->collection_year) }}"></div>
                    <div class="col-md-4"><label class="form-label">Quantity Gaddi {{ $index + 1 }}</label><input class="form-control" name="tendupatta_collections[{{ $index }}][quantity_gaddi]" value="{{ old("tendupatta_collections.$index.quantity_gaddi", $collection?->quantity_gaddi) }}"></div>
                    <div class="col-md-4"><label class="form-label">Data Source</label><input class="form-control" value="MANUAL" readonly><input type="hidden" name="tendupatta_collections[{{ $index }}][data_source]" value="MANUAL"></div>
                @endfor
            </div>
        </x-card>

        <x-card title="Student Bank Details / छात्र बैंक विवरण" icon="fa-solid fa-building-columns" class="mb-3">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Account Number / खाता संख्या <span class="text-danger">*</span></label><input class="form-control only-numbers" type="password" name="student_bank_account_number" id="student_bank_account_number" value="{{ old('student_bank_account_number', $application?->student_bank_account_number) }}" required></div>
                <div class="col-md-4"><label class="form-label">Confirm Account Number / पुष्टि करें <span class="text-danger">*</span></label><input class="form-control only-numbers" id="confirm_student_bank_account_number" value="{{ old('student_bank_account_number', $application?->student_bank_account_number) }}" required><div class="small text-danger"></div></div>
                <div class="col-md-4"><label class="form-label">Account Holder Name / खाताधारक <span class="text-danger">*</span></label><input class="form-control only-chars" name="student_bank_account_holder_name" id="student_bank_account_holder_name" value="{{ old('student_bank_account_holder_name', $application?->student_bank_account_holder_name) }}" required><div class="small text-danger"></div></div>
                <div class="col-md-4"><label class="form-label">IFSC Code / आईएफएससी <span class="text-danger">*</span></label><input class="form-control uppercase-input" name="student_bank_ifsc" id="student_bank_ifsc" value="{{ old('student_bank_ifsc', $application?->student_bank_ifsc) }}" required></div>
                <div class="col-md-4"><label class="form-label">Bank Name / बैंक <span class="text-danger">*</span></label><input class="form-control" name="student_bank_name" id="student_bank_name" value="{{ old('student_bank_name', $application?->student_bank_name) }}" required></div>
                <div class="col-md-4"><label class="form-label">Branch Name / शाखा <span class="text-danger">*</span></label><input class="form-control" name="student_bank_branch" id="student_bank_branch" value="{{ old('student_bank_branch', $application?->student_bank_branch) }}" required></div>
            </div>
        </x-card>

        <x-card title="Attach Document / दस्तावेज़ संलग्न करें" icon="fa-solid fa-paperclip" class="mb-3">
            <div class="row g-3">
                @foreach([
                    'tpcard' => 'Sangrahak Card / संग्राहक कार्ड',
                    'haadharcard' => 'Head of Family Aadhaar Card / परिवार मुखिया आधार कार्ड',
                    'aadharcard' => 'Aadhaar Card of Student / छात्र का आधार कार्ड',
                    'admission_copy' => 'Marksheet Copy / मार्कशीट कॉपी',
                    'passbook' => 'Student Bank Passbook / छात्र बैंक पासबुक',
                    'admission_receipt' => 'Admission Receipt / प्रवेश रसीद',
                ] as $type => $label)
                    @if($type !== 'admission_receipt' || $isCourseScheme)
                        <div class="col-md-6">
                            <label class="form-label">{{ $label }} <span class="text-danger">*</span></label>
                            <input class="form-control" type="file" name="document_uploads[{{ $type }}]" @required(! $documents->has($type))>
                            <div class="form-text">Max size: 2MB. Allowed: jpg, jpeg, png, pdf.</div>
                            @if($documents->has($type))
                                <div class="small text-muted">Current: {{ $documents->get($type)?->file_path }}</div>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        </x-card>

        <div class="d-flex justify-content-end gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('applications.index') }}">Cancel</a>
            @if(! $application || $application->is_draft)
                <button class="btn btn-outline-primary" name="intent" value="draft" type="submit"><i class="fa-regular fa-floppy-disk me-1"></i>Save Draft</button>
                <button class="btn btn-primary" name="intent" value="submit" type="submit"><i class="fa-solid fa-paper-plane me-1"></i>Preview / Submit</button>
            @else
                <button class="btn btn-primary" name="intent" value="resubmit" type="submit"><i class="fa-solid fa-rotate me-1"></i>Resubmit</button>
            @endif
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const endpoint = (path, params) => `/api/scholarship/lookups/${path}?${new URLSearchParams(params)}`;
            const choose = '<option value="">--CHOOSE--</option>';

            function fillSelect(select, items, selected, valueKey = 'id') {
                select.innerHTML = choose + items.map((item) => `<option value="${item[valueKey]}" ${String(item[valueKey]) === String(selected) ? 'selected' : ''} data-number="${item.number || ''}">${item.name}</option>`).join('');
            }

            async function load(select, path, params, valueKey = 'id') {
                const response = await fetch(endpoint(path, params), {headers: {'Accept': 'application/json'}});
                fillSelect(select, response.ok ? await response.json() : [], select.dataset.selected, valueKey);
            }

            const scheme = document.getElementById('scheme_id');
            const district = document.getElementById('district_id');
            const districtUnion = document.getElementById('district_union_id');
            const samiti = document.getElementById('samiti_id');
            const phad = document.getElementById('phad_id');
            const block = document.getElementById('block_code');
            const gramPanchayat = document.getElementById('gram_panchayat_code');
            const village = document.getElementById('village_code');
            const city = document.getElementById('city_code');
            const ward = document.getElementById('ward_code');
            const area = document.getElementById('area');

            function toggleScheme() {
                const isCourse = ['3', '4'].includes(scheme.value);
                document.querySelectorAll('.course-field').forEach((node) => node.style.display = isCourse ? '' : 'none');
                document.querySelectorAll('.course-field input, .course-field select').forEach((node) => node.required = isCourse && !['first_year_session', 'scholarship_session'].includes(node.name));
            }

            function toggleArea() {
                const rural = area.value === 'Rural';
                const urban = area.value === 'Urban';
                document.querySelectorAll('.area-rural').forEach((node) => node.style.display = rural ? '' : 'none');
                document.querySelectorAll('.area-urban').forEach((node) => node.style.display = urban ? '' : 'none');
                [gramPanchayat, village].forEach((node) => node.required = rural);
                [city, ward].forEach((node) => node.required = urban);
            }

            function calculatePercentage() {
                const obtained = Number(document.getElementById('marks_obtained').value || 0);
                const total = Number(document.getElementById('maximum_marks').value || 0);
                document.getElementById('percentage').value = total > 0 ? ((obtained * 100) / total).toFixed(2) : '';
            }

            districtUnion.addEventListener('change', () => load(samiti, 'samitis', {district_union_id: districtUnion.value}));
            samiti.addEventListener('change', () => load(phad, 'phads', {samiti_id: samiti.value}));
            district.addEventListener('change', () => load(block, 'blocks', {district_code: district.value}, 'code'));
            block.addEventListener('change', () => {
                load(gramPanchayat, 'gram-panchayats', {block_code: block.value}, 'code');
                load(city, 'cities', {block_code: block.value}, 'code');
            });
            gramPanchayat.addEventListener('change', () => load(village, 'villages', {gram_panchayat_code: gramPanchayat.value}, 'code'));
            city.addEventListener('change', () => load(ward, 'wards', {city_code: city.value}, 'code'));
            ward.addEventListener('change', () => document.getElementById('ward_number').value = ward.selectedOptions[0]?.dataset.number || '');
            area.addEventListener('change', toggleArea);
            scheme.addEventListener('change', toggleScheme);
            document.getElementById('marks_obtained').addEventListener('input', calculatePercentage);
            document.getElementById('maximum_marks').addEventListener('input', calculatePercentage);

            document.querySelectorAll('.only-numbers').forEach((input) => input.addEventListener('input', () => input.value = input.value.replace(/\D/g, '')));
            document.querySelectorAll('.only-chars').forEach((input) => input.addEventListener('input', () => input.value = input.value.replace(/[^a-zA-Z ]/g, '')));
            document.querySelectorAll('.uppercase-input').forEach((input) => input.addEventListener('input', () => input.value = input.value.toUpperCase()));
            document.querySelectorAll('.aadhaar-field').forEach((input) => input.addEventListener('input', () => {
                input.nextElementSibling.textContent = input.value.length > 0 && input.value.length !== 12 ? 'Aadhaar number will be of 12 digits' : '';
                if (document.getElementById('student_aadhaar').value && document.getElementById('student_aadhaar').value === document.getElementById('head_of_family_aadhaar').value) {
                    input.nextElementSibling.textContent = 'Student and Head of Family Aadhaar cannot be same';
                }
            }));
            document.getElementById('confirm_student_bank_account_number').addEventListener('input', (event) => {
                event.target.nextElementSibling.textContent = event.target.value !== document.getElementById('student_bank_account_number').value ? 'Account Number not matched.' : '';
            });
            document.getElementById('student_bank_account_holder_name').addEventListener('input', (event) => {
                const clean = (value) => value.toLowerCase().replace(/[^a-z0-9]/g, '');
                event.target.nextElementSibling.textContent = clean(event.target.value) !== clean(document.getElementById('student_name').value) ? 'Account holder name must match student name.' : '';
            });
            document.getElementById('student_bank_ifsc').addEventListener('change', async (event) => {
                if (event.target.value.length < 8) return;
                const response = await fetch(`https://ifsc.razorpay.com/${event.target.value}`);
                if (!response.ok) return;
                const bank = await response.json();
                document.getElementById('student_bank_name').value = bank.BANK || document.getElementById('student_bank_name').value;
                document.getElementById('student_bank_branch').value = bank.BRANCH ? `${bank.BRANCH}, ${bank.ADDRESS || ''}` : document.getElementById('student_bank_branch').value;
            });

            toggleScheme();
            toggleArea();
            calculatePercentage();
            if (district.value) {
                load(block, 'blocks', {district_code: district.value}, 'code').then(() => {
                    if (block.dataset.selected) {
                        load(gramPanchayat, 'gram-panchayats', {block_code: block.dataset.selected}, 'code');
                        load(city, 'cities', {block_code: block.dataset.selected}, 'code');
                    }
                });
            }
        });
    </script>
@endsection
