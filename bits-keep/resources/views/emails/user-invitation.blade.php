<p>{{ $user->name }} さん</p>

<p>BitsKeep へ招待されました。以下の情報でログインできます。</p>

<ul>
    <li>ログインURL: <a href="{{ route('login') }}">{{ route('login') }}</a></li>
    <li>メールアドレス: {{ $user->email }}</li>
    <li>仮パスワード: {{ $temporaryPassword }}</li>
    <li>ロール: {{ $user->role }}</li>
</ul>

<p>ログイン後は、必要に応じてパスワードを変更してください。</p>
