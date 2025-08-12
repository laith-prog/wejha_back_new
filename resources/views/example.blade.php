@extends('layouts.app')

@section('content')
<div class="container" style="padding: 20px;">
    <h1>{{ __('messages.welcome') }}</h1>
    
    <div class="card" style="margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
        <h2>{{ __('messages.dashboard') }}</h2>
        <p>{{ __('messages.profile') }}</p>
        
        <div class="actions" style="margin-top: 20px;">
            <button style="padding: 10px 15px; margin-right: 10px; background-color: #4CAF50; color: white; border: none; border-radius: 4px;">
                {{ __('messages.save') }}
            </button>
            <button style="padding: 10px 15px; background-color: #f44336; color: white; border: none; border-radius: 4px;">
                {{ __('messages.cancel') }}
            </button>
        </div>
    </div>
    
    <div class="user-section" style="margin: 20px 0;">
        <h3>{{ __('messages.users') }}</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">{{ __('messages.name') }}</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">{{ __('messages.email') }}</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">{{ __('messages.role') }}</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">{{ __('messages.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px;">John Doe</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">john@example.com</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">{{ __('messages.role') }}</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">
                        <a href="#" style="color: blue; text-decoration: none; margin-right: 10px;">{{ __('messages.edit') }}</a>
                        <a href="#" style="color: red; text-decoration: none;">{{ __('messages.delete') }}</a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection 