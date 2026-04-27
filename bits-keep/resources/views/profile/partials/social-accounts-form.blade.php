<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            SNSログイン連携
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Google / GitHub ログインはここで追加・解除します。連携先を変更する場合は、現在の連携を解除してから新しいアカウントを追加してください。
        </p>
    </header>

    @if ($errors->has('social'))
        <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first('social') }}
        </div>
    @endif

    @if (session('status') === 'social-linked')
        <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            SNSアカウントを連携しました。
        </div>
    @endif

    @if (session('status') === 'social-unlinked')
        <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            SNSアカウントの連携を解除しました。
        </div>
    @endif

    @php
        $providers = [
            'google' => 'Google',
            'github' => 'GitHub',
        ];
        $linkedProviders = $user->authProviders->keyBy('provider');
    @endphp

    <div class="mt-6 space-y-3">
        @foreach ($providers as $provider => $label)
            @php($link = $linkedProviders->get($provider))
            <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 px-4 py-3">
                <div>
                    <div class="font-medium text-gray-900">{{ $label }}</div>
                    <div class="mt-1 text-sm text-gray-500">
                        @if ($link)
                            {{ $link->provider_email ?: 'メール未取得' }} / 最終利用 {{ optional($link->last_used_at)->format('Y-m-d') ?: '-' }}
                        @else
                            未連携
                        @endif
                    </div>
                </div>

                @if ($link)
                    <form method="POST" action="{{ route('auth.social.unlink', ['provider' => $provider]) }}">
                        @csrf
                        @method('DELETE')
                        <x-secondary-button>
                            解除
                        </x-secondary-button>
                    </form>
                @else
                    <a href="{{ route('auth.social.link', ['provider' => $provider]) }}"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                        追加
                    </a>
                @endif
            </div>
        @endforeach
    </div>
</section>
