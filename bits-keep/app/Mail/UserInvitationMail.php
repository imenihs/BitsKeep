<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;
    /**
     * 目的: UserInvitationメールの依存オブジェクトを受け取り、後続処理で使える状態にする。
     * 機能: 呼び出し元から受けた値を検証または整形し、対象処理へ渡す。
     * 入力: 関数シグネチャで指定された引数。
     * 出力: 型宣言または呼び出し規約に従う処理結果。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと入力値を渡すこと。
     * 副作用: 依存オブジェクト、DB、ファイル、外部API、モデル状態のいずれかを更新する場合がある。
     */
    public function __construct(
        public User $user,
        public string $temporaryPassword
    ) {}
    /**
     * 目的: User Invitation Mailのenvelopeを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: なし。
     * 出力: Envelopeで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'BitsKeep へ招待されました',
        );
    }
    /**
     * 目的: User Invitation Mailのcontentを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: なし。
     * 出力: Contentで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.user-invitation',
        );
    }
}
