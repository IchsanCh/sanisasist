// resources/js/pages/auth/two-factor-challenge.tsx

import { Head, useForm } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import AuthLayout from '@/layouts/auth-layout';

export default function TwoFactorChallenge() {
    const [showRecoveryInput, setShowRecoveryInput] = useState(false);
    const [code, setCode] = useState('');

    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        recovery_code: '',
    });

    const authConfig = useMemo(() => {
        if (showRecoveryInput)
            return {
                title: 'Recovery code',
                description:
                    'Please confirm access to your account by entering one of your emergency recovery codes.',
                toggleText: 'login using an authentication code',
            };
        return {
            title: 'Authentication code',
            description:
                'Enter the authentication code provided by your authenticator application.',
            toggleText: 'login using a recovery code',
        };
    }, [showRecoveryInput]);

    const submit = () =>
        post('/two-factor-challenge', {
            onError: () => reset('code', 'recovery_code'),
        });

    const toggleMode = () => {
        setShowRecoveryInput((prev) => !prev);
        setCode('');
        reset();
    };

    return (
        <AuthLayout
            title={authConfig.title}
            description={authConfig.description}
        >
            <Head title="Two-factor authentication" />

            <div className="space-y-6">
                <div className="space-y-4">
                    {showRecoveryInput ? (
                        <>
                            <Input
                                type="text"
                                placeholder="Enter recovery code"
                                autoFocus
                                value={data.recovery_code}
                                onChange={(e) =>
                                    setData('recovery_code', e.target.value)
                                }
                            />
                            <InputError message={errors.recovery_code} />
                        </>
                    ) : (
                        <div className="flex flex-col items-center space-y-3 text-center">
                            <InputOTP
                                maxLength={OTP_MAX_LENGTH}
                                value={code}
                                onChange={(v) => {
                                    setCode(v);
                                    setData('code', v);
                                }}
                                disabled={processing}
                                pattern={REGEXP_ONLY_DIGITS}
                            >
                                <InputOTPGroup>
                                    {Array.from(
                                        { length: OTP_MAX_LENGTH },
                                        (_, i) => (
                                            <InputOTPSlot key={i} index={i} />
                                        ),
                                    )}
                                </InputOTPGroup>
                            </InputOTP>
                            <InputError message={errors.code} />
                        </div>
                    )}

                    <Button
                        className="w-full"
                        disabled={processing}
                        onClick={submit}
                    >
                        Continue
                    </Button>

                    <div className="text-center text-sm text-muted-foreground">
                        <span>or you can </span>
                        <button
                            type="button"
                            className="cursor-pointer text-foreground underline underline-offset-4"
                            onClick={toggleMode}
                        >
                            {authConfig.toggleText}
                        </button>
                    </div>
                </div>
            </div>
        </AuthLayout>
    );
}
