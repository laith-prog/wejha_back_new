<div class="language-switcher">
    <a href="{{ route('language.switch', 'en') }}" class="{{ app()->getLocale() == 'en' ? 'active' : '' }}">
        English
    </a>
    |
    <a href="{{ route('language.switch', 'ar') }}" class="{{ app()->getLocale() == 'ar' ? 'active' : '' }}">
        العربية
    </a>
</div>

<style>
    .language-switcher {
        padding: 10px;
        text-align: right;
    }
    .language-switcher a {
        padding: 5px;
        text-decoration: none;
    }
    .language-switcher a.active {
        font-weight: bold;
        text-decoration: underline;
    }
    /* For RTL support when Arabic is selected */
    html[lang="ar"] body {
        direction: rtl;
        text-align: right;
    }
</style> 