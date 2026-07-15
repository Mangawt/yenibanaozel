@extends('layouts.app')

@section('content')
    <section class="legal-page legal-rich">
        <p class="eyebrow">Son güncelleme: 15.07.2026</p>
        <h1>Çerez Politikası</h1>
        <p>Nozu.me, hizmetin çalışmasını sağlamak, kullanıcı tercihlerini hatırlamak, güvenliği korumak ve kullanıcı onayı bulunması halinde kullanım istatistikleri elde etmek amacıyla çerezlerden yararlanabilir.</p>

        <h2>Zorunlu çerezler</h2>
        <p>Oturum açma, güvenlik, yük dengeleme, CSRF koruması ve çerez tercihinin saklanması gibi temel işlevler için gereklidir.</p>

        <h2>Tercih çerezleri</h2>
        <p>Tema, dil, görünüm ve benzeri kullanıcı seçimlerini hatırlamak için kullanılabilir.</p>

        <h2>Analitik çerezler</h2>
        <p>Platformun nasıl kullanıldığını anlamak, performansı ölçmek ve hataları gidermek amacıyla kullanılabilir. Gerekli durumlarda yalnızca kullanıcı onayı sonrasında etkinleştirilir.</p>

        <h2>Çerez tercihleri</h2>
        <p>Kullanıcılar zorunlu olmayan çerezleri reddedebilir, kategori bazında tercih yapabilir veya daha önce verdiği izni geri çekebilir.</p>

        <div class="legal-table">
            <div><strong>nozu_session</strong><span>Kullanıcı oturumunu korumak için zorunlu çerez.</span></div>
            <div><strong>XSRF-TOKEN</strong><span>Form ve istek güvenliği için zorunlu çerez.</span></div>
            <div><strong>nozu-theme</strong><span>Tema tercihini saklayan tercih kaydı.</span></div>
        </div>
    </section>
@endsection
