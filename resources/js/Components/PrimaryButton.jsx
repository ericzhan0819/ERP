export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex w-full items-center rounded-xl border border-active bg-accent px-4 py-2 text-xs font-semibold uppercase tracking-widest text-inverse shadow-card transition duration-150 ease-in-out hover:bg-hover hover:text-primary focus:bg-active focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-2 active:bg-active ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
