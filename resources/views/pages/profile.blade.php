@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="User Profile" />
    <div class="content-card p-5 lg:p-6">
        <h3 class="mb-5 text-lg font-semibold text-gray-800 dark:text-white/90 lg:mb-7">Profile</h3>
        <x-profile.profile-card />
        <x-profile.personal-info-card />
        <x-profile.address-card />
    </div>
@endsection
