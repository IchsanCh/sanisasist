// resources/js/components/two-factor-setup-modal.tsx

import { useForm } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { Check, Copy, ScanLine } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import AlertError from '@/components/alert-error';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { useAppearance } from '@/hooks/use-appearance';
import { useClipboard } from '@/hooks/use-clipboard';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';

function GridScanIcon() {
    return (
        <div className="mb-3 rounded-full border border-border bg-card p-0.5 shadow-sm">
            <div className="relative overflow-hidden rounded-full border border-border bg-muted p-2.5">
                <div className="absolute inset-0 grid grid-cols-5 opacity-50">
                    {Array.from({ length: 5 }, (_, i) => (
                        <div
                            key={i}
                            className="border-r border-border last:border-r-0"
                        />
                    ))}
                </div>
                <div className="absolute inset-0 grid grid-rows-5 opacity-50">
                    {Array.from({ length: 5 }, (_, i) => (
                        <div
                            key={i}
                            className="border-b border-border last:border-b-0"
                        />
                    ))}
                </div>
                <ScanLine className="relative z-20 size-6 text-foreground" />
            </div>
        </div>
    );
}

function TwoFactorVerificationStep({
    onClose,
    onBack,
}: {
    onClose: () => void;
    onBack: () => void;
}) {
    const [code, setCode] = useState('');
    const pinInputRef = useRef<HTMLDivElement>(null);
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
    });

    useEffect(() => {
        setTimeout(
            () => pinInputRef.current?.querySelector('input')?.focus(),
            0,
        );
    }, []);

    const submit = () => {
        post('/user/confirmed-two-factor-authentication', {
            onSuccess: () => {
                reset();
                onClose();
            },
            onError: () => {
                reset();
                setCode('');
            },
        });
    };

    return (
        <div ref={pinInputRef} className="relative w-full space-y-3">
            <div className="flex w-full flex-col items-center space-y-3 py-2">
                <InputOTP
                    maxLength={OTP_MAX_LENGTH}
                    onChange={(v) => {
                        setCode(v);
                        setData('code', v);
                    }}
                    disabled={processing}
                    pattern={REGEXP_ONLY_DIGITS}
                >
                    <InputOTPGroup>
                        {Array.from({ length: OTP_MAX_LENGTH }, (_, i) => (
                            <InputOTPSlot key={i} index={i} />
                        ))}
                    </InputOTPGroup>
                </InputOTP>
                <InputError message={errors.code} />
            </div>
            <div className="flex w-full space-x-5">
                <Button
                    type="button"
                    variant="outline"
                    className="flex-1"
                    onClick={onBack}
                    disabled={processing}
                >
                    Back
                </Button>
                <Button
                    className="flex-1"
                    disabled={processing || code.length < OTP_MAX_LENGTH}
                    onClick={submit}
                >
                    Confirm
                </Button>
            </div>
        </div>
    );
}

type Props = {
    isOpen: boolean;
    onClose: () => void;
    requiresConfirmation: boolean;
    twoFactorEnabled: boolean;
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    clearSetupData: () => void;
    fetchSetupData: () => Promise<void>;
    errors: string[];
};

export default function TwoFactorSetupModal({
    isOpen,
    onClose,
    requiresConfirmation,
    twoFactorEnabled,
    qrCodeSvg,
    manualSetupKey,
    clearSetupData,
    fetchSetupData,
    errors,
}: Props) {
    const [showVerificationStep, setShowVerificationStep] = useState(false);
    const { resolvedAppearance } = useAppearance();
    const [copiedText, copy] = useClipboard();
    const CopyIcon = copiedText === manualSetupKey ? Check : Copy;

    const modalConfig = useMemo(() => {
        if (twoFactorEnabled)
            return {
                title: 'Two-factor authentication enabled',
                description:
                    'Scan the QR code or enter the setup key in your authenticator app.',
                buttonText: 'Close',
            };
        if (showVerificationStep)
            return {
                title: 'Verify authentication code',
                description:
                    'Enter the 6-digit code from your authenticator app',
                buttonText: 'Continue',
            };
        return {
            title: 'Enable two-factor authentication',
            description:
                'Scan the QR code or enter the setup key in your authenticator app',
            buttonText: 'Continue',
        };
    }, [twoFactorEnabled, showVerificationStep]);

    const handleNextStep = useCallback(() => {
        if (requiresConfirmation) {
            setShowVerificationStep(true);
            return;
        }
        clearSetupData();
        onClose();
    }, [requiresConfirmation, clearSetupData, onClose]);

    const handleClose = useCallback(() => {
        setShowVerificationStep(false);
        if (twoFactorEnabled) clearSetupData();
        onClose();
    }, [twoFactorEnabled, clearSetupData, onClose]);

    useEffect(() => {
        if (isOpen && !qrCodeSvg) fetchSetupData();
    }, [isOpen, qrCodeSvg, fetchSetupData]);

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && handleClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader className="flex items-center justify-center">
                    <GridScanIcon />
                    <DialogTitle>{modalConfig.title}</DialogTitle>
                    <DialogDescription className="text-center">
                        {modalConfig.description}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col items-center space-y-5">
                    {showVerificationStep ? (
                        <TwoFactorVerificationStep
                            onClose={onClose}
                            onBack={() => setShowVerificationStep(false)}
                        />
                    ) : (
                        <>
                            {errors?.length ? (
                                <AlertError errors={errors} />
                            ) : (
                                <>
                                    <div className="mx-auto flex max-w-md overflow-hidden">
                                        <div className="mx-auto aspect-square w-64 rounded-lg border border-border">
                                            <div className="z-10 flex h-full w-full items-center justify-center p-5">
                                                {qrCodeSvg ? (
                                                    <div
                                                        className="aspect-square w-full rounded-lg bg-white p-2 [&_svg]:size-full"
                                                        dangerouslySetInnerHTML={{
                                                            __html: qrCodeSvg,
                                                        }}
                                                        style={{
                                                            filter:
                                                                resolvedAppearance ===
                                                                'dark'
                                                                    ? 'invert(1) brightness(1.5)'
                                                                    : undefined,
                                                        }}
                                                    />
                                                ) : (
                                                    <Spinner />
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    <Button
                                        className="w-full"
                                        onClick={handleNextStep}
                                    >
                                        {modalConfig.buttonText}
                                    </Button>

                                    <div className="relative flex w-full items-center justify-center">
                                        <div className="absolute inset-0 top-1/2 h-px w-full bg-border" />
                                        <span className="relative bg-card px-2 py-1">
                                            or, enter the code manually
                                        </span>
                                    </div>

                                    <div className="flex w-full space-x-2">
                                        <div className="flex w-full items-stretch overflow-hidden rounded-xl border border-border">
                                            {!manualSetupKey ? (
                                                <div className="flex h-full w-full items-center justify-center bg-muted p-3">
                                                    <Spinner />
                                                </div>
                                            ) : (
                                                <>
                                                    <input
                                                        type="text"
                                                        readOnly
                                                        value={manualSetupKey}
                                                        className="h-full w-full bg-background p-3 text-foreground outline-none"
                                                    />
                                                    <button
                                                        onClick={() =>
                                                            copy(manualSetupKey)
                                                        }
                                                        className="border-l border-border px-3 hover:bg-muted"
                                                    >
                                                        <CopyIcon className="w-4" />
                                                    </button>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </>
                            )}
                        </>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
