@extends('layouts.app')

@section('content')
    <section class="settings-page">
        <header class="settings-page-header">
            <div class="settings-page-icon">
                <i class="fa-solid fa-gear"></i>
            </div>

            <div>
                <p class="eyebrow">nozu.me hesabı</p>

                <h1>Ayarlar</h1>

                <p>
            içerikler tamamen silinebilir.venliği, gizlilik ve yasal ayarlarını
                    buradan yönetebilirsin.
                </p>
            </div>
        </header>

        <div class="settings-profile-summary">
            <div class="settings-profile-avatar">
                @if($user->avatar_path)
                    <img
                        src="{{ app(\App\Services\UserMediaStorage::class)->url($user->avatar_path) }}"
                        alt="{{ $user->username }}"
                    >
                @else
                    <span>
                        {{ mb_strtoupper(mb_substr($user->username ?: 'N', 0, 1)) }}
                    </span>
                @endif
            </div>

            <div class="settings-profile-info">
                <strong>{{ $user->name }}</strong>

                <span>{{ '@'.$user->username }}</span>

                <small>{{ $user->email }}</small>
            </div>

            <a
                href="{{ route('profile.edit') }}"
                class="settings-profile-edit"
            >
                <i class="fa-regular fa-pen-to-square"></i>

                <span>Profili Düzenle</span>
            </a>
        </div>

        <section class="settings-section">
            <div class="settings-section-heading">
                <h2>Hesap ve Güvenlik</h2>

                <p>
                    Hesabını ve güvenlik seçeneklerini yönet.
                </p>
            </div>

            <div class="settings-list">
                <button
                    type="button"
                    class="settings-row settings-row-danger"
                    id="show-delete-account"
                >
                    <span class="settings-row-icon">
                        <i class="fa-regular fa-trash-can"></i>
                    </span>

                    <span class="settings-row-content">
                        <strong>Hesabımı Sil</strong>

                        <small>
                            Hesabını ve bağlantılı kişisel verilerini
                            kalıcı olarak sil.
                        </small>
                    </span>

                    <i class="fa-solid fa-chevron-right settings-row-arrow"></i>
                </button>
            </div>
        </section>

        <section class="settings-section">
            <div class="settings-section-heading">
                <h2>Yasal ve Gizlilik</h2>

                <p>
                    Nozu politikalarını ve veri işlemlerini incele.
                </p>
            </div>

            <div class="settings-list">
                <a
                    href="{{ route('privacy') }}"
                    class="settings-row"
                >
                    <span class="settings-row-icon">
                        <i class="fa-solid fa-shield-halved"></i>
                    </span>

                    <span class="settings-row-content">
                        <strong>Gizlilik Politikası</strong>

                        <small>
                            Kişisel verilerinin nasıl işlendiğini incele.
                        </small>
                    </span>

                    <i class="fa-solid fa-chevron-right settings-row-arrow"></i>
                </a>

                <div class="settings-row-divider"></div>

                <a
                    href="{{ route('terms') }}"
                    class="settings-row"
                >
                    <span class="settings-row-icon">
                        <i class="fa-regular fa-file-lines"></i>
                    </span>

                    <span class="settings-row-content">
                        <strong>Kullanım Şartları</strong>

                        <small>
                            Nozu hizmetlerinin kullanım koşullarını incele.
                        </small>
                    </span>

                    <i class="fa-solid fa-chevron-right settings-row-arrow"></i>
                </a>

                <div class="settings-row-divider"></div>

                <a
                    href="{{ route('account-deletion') }}"
                    class="settings-row"
                >
                    <span class="settings-row-icon">
                        <i class="fa-solid fa-user-shield"></i>
                    </span>

                    <span class="settings-row-content">
                        <strong>Hesap ve Veri Silme Bilgileri</strong>

                        <small>
                            Silinen ve anonim tutulan veriler hakkında
                            bilgi al.
                        </small>
                    </span>

                    <i class="fa-solid fa-chevron-right settings-row-arrow"></i>
                </a>
            </div>
        </section>

        <section
            id="delete-account-area"
            class="delete-account-panel"
            @if(! $errors->has('password') && ! $errors->has('confirmed'))
                hidden
            @endif
        >
            <div class="delete-account-warning">
                <div class="delete-account-warning-icon">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>

                <div>
                    <strong>Bu işlem geri alınamaz</strong>

                    <p>
                        Hesabın silindiğinde profilin, listelerin,
                        favorilerin, takip bağlantıların ve aktif
                        oturumların kalıcı olarak silinir.
                    </p>
                </div>
            </div>

            <div class="delete-account-data">
                <h3>Silinecek veriler</h3>

                <ul>
                    <li>
                        <i class="fa-solid fa-xmark"></i>
                        Profil ve hesap bilgileri
                    </li>

                    <li>
                        <i class="fa-solid fa-xmark"></i>
                        Anime ve manga listeleri
                    </li>

                    <li>
                        <i class="fa-solid fa-xmark"></i>
                        Favoriler ve puanlar
                    </li>

                    <li>
                        <i class="fa-solid fa-xmark"></i>
                        Takip bağlantıları
                    </li>

                    <li>
                        <i class="fa-solid fa-xmark"></i>
                        Bildirimler ve aktif oturumlar
                    </li>

                    <li>
                        <i class="fa-solid fa-xmark"></i>
                        Avatar ve kapak görseli
                    </li>
                </ul>

                <p class="delete-account-note">
                    Yorumların, konuşma bütünlüğünün korunması amacıyla
                    hesabınla bağlantısı kaldırılarak anonim tutulabilir.
                </p>
            </div>

            <form
                class="delete-account-form"
                method="post"
                action="{{ route('account.destroy') }}"
                id="delete-account-form"
            >
                @csrf
                @method('DELETE')

                <h3>Kimliğini doğrula</h3>

                @if($requiresGoogleVerification)
                    @if($googleDeletionVerified)
                        <div class="google-delete-verified">
                            <i class="fa-solid fa-circle-check"></i>

                            <div>
                                <strong>Google hesabın doğrulandı</strong>

                                <span>
                                    Bu doğrulama 10 dakika boyunca geçerlidir.
                                </span>
                            </div>
                        </div>
                    @else
                        <p>
                            Hesabını silmek için bağlı Google hesabını
                            yeniden doğrulamalısın.
                        </p>

                        <a
                            href="{{ route('account.google.verify') }}"
                            class="google-auth-button google-delete-button"
                        >
                            <svg
                                width="20"
                                height="20"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.39-.18-2.05H12v3.87h5.38a4.6 4.6 0 0 1-2 3.02v2.51h3.24c1.9-1.75 2.98-4.33 2.98-7.35z"/>
                                <path fill="#34A853" d="M12 22c2.7 0 4.97-.9 6.62-2.42l-3.24-2.51c-.9.6-2.05.96-3.38.96-2.61 0-4.82-1.76-5.61-4.13H3.04v2.59A10 10 0 0 0 12 22z"/>
                                <path fill="#FBBC05" d="M6.39 13.9A6.02 6.02 0 0 1 6.08 12c0-.66.11-1.3.31-1.9V7.51H3.04A10 10 0 0 0 2 12c0 1.61.38 3.14 1.04 4.49l3.35-2.59z"/>
                                <path fill="#EA4335" d="M12 5.97c1.47 0 2.79.51 3.83 1.5l2.87-2.87A9.64 9.64 0 0 0 12 2a10 10 0 0 0-8.96 5.51l3.35 2.59C7.18 7.73 9.39 5.97 12 5.97z"/>
                            </svg>

                            <span>Google ile Doğrula</span>
                        </a>
                    @endif
                @else
                    <p>
                        Hesabını silmek için mevcut şifreni gir.
                    </p>

                    <label>
                        <span>Mevcut şifre</span>

                        <div class="password-input-wrap">
                            <i class="fa-solid fa-lock"></i>

                            <input
                                id="delete-account-password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                required
                            >

                            <button
                                type="button"
                                class="password-visibility-toggle"
                                aria-label="Şifreyi göster"
                            >
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </label>

                    @error('password')
                        <p class="form-error">
                            {{ $message }}
                        </p>
                    @enderror
                @endif

                @error('google')
                    <p class="form-error">
                        {{ $message }}
                    </p>
                @enderror

                <label class="delete-account-checkbox">
                    <input
                        type="checkbox"
                        name="confirmed"
                        value="1"
                        required
                    >

                    <span>Onaylıyorum</span>
                </label>

                @error('confirmed')
                    <p class="form-error">
                        {{ $message }}
                    </p>
                @enderror

                <div class="delete-account-actions">
                    <button
                        type="submit"
                        class="delete-account-submit"
                        @if($requiresGoogleVerification && ! $googleDeletionVerified)
                            disabled
                        @endif
                    >
                        <i class="fa-regular fa-trash-can"></i>

                        Hesabımı Kalıcı Olarak Sil
                    </button>

                    <button
                        type="button"
                        class="delete-account-cancel"
                        id="cancel-delete-account"
                    >
                        Vazgeç
                    </button>
                </div>
            </form>
        </section>
    </section>

    <div
        class="delete-account-modal"
        id="delete-account-modal"
        hidden
    >
        <div
            class="delete-account-modal-backdrop"
            data-close-delete-modal
        ></div>

        <div
            class="delete-account-modal-card"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-account-modal-title"
        >
            <div class="delete-account-modal-icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <h2 id="delete-account-modal-title">
                Hesabın kalıcı olarak silinsin mi?
            </h2>

            <p>
                Bu işlemin geri dönüşü yoktur. Hesabın ve bağlantılı
                verilerin kalıcı olarak silinecektir.
            </p>

            <div class="delete-account-modal-actions">
                <button
                    type="button"
                    class="delete-account-cancel"
                    data-close-delete-modal
                >
                    Vazgeç
                </button>

                <button
                    type="button"
                    class="delete-account-submit"
                    id="confirm-delete-account"
                >
                    Kalıcı Olarak Sil
                </button>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const showButton = document.getElementById(
                'show-delete-account'
            );

            const panel = document.getElementById(
                'delete-account-area'
            );

            const cancelButton = document.getElementById(
                'cancel-delete-account'
            );

            const form = document.getElementById(
                'delete-account-form'
            );

            const modal = document.getElementById(
                'delete-account-modal'
            );

            const confirmButton = document.getElementById(
                'confirm-delete-account'
            );

            const passwordInput = document.getElementById(
                'delete-account-password'
            );

            const visibilityButton = document.querySelector(
                '.password-visibility-toggle'
            );

            showButton?.addEventListener('click', () => {
                panel.hidden = false;

                panel.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });

                window.setTimeout(() => {
                    passwordInput?.focus();
                }, 350);
            });

            cancelButton?.addEventListener('click', () => {
                panel.hidden = true;

                showButton?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                });
            });

            visibilityButton?.addEventListener('click', () => {
                if (!passwordInput) {
                    return;
                }

                const hidden = passwordInput.type === 'password';

                passwordInput.type = hidden
                    ? 'text'
                    : 'password';

                visibilityButton.innerHTML = hidden
                    ? '<i class="fa-regular fa-eye-slash"></i>'
                    : '<i class="fa-regular fa-eye"></i>';

                visibilityButton.setAttribute(
                    'aria-label',
                    hidden
                        ? 'ifreyi gizle'
                        : 'Şifreyi göster'
                );
            });

            form?.addEventListener('submit', (event) => {
                if (form.dataset.confirmed === 'true') {
                    return;
                }

                event.preventDefault();

                modal.hidden = false;

                document.body.classList.add(
                    'delete-modal-open'
                );
            });

            document
                .querySelectorAll('[data-close-delete-modal]')
                .forEach((button) => {
                    button.addEventListener('click', () => {
                        modal.hidden = true;

                        document.body.classList.remove(
                            'delete-modal-open'
                        );
                    });
                });

            confirmButton?.addEventListener('click', () => {
                form.dataset.confirmed = 'true';

                modal.hidden = true;

                document.body.classList.remove(
                    'delete-modal-open'
                );

                form.requestSubmit();
            });
        })();
    </script>
@endsection
