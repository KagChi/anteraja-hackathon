import { useEffect } from "react";
import { ScreenLoading } from "../../components/StateCard";
import { useRouter } from "../../lib/router";
import { isSignedIn, signedInHome, signedOutEntry } from "../../lib/token";

/**
 * The gate the app opens on.
 *
 * A signed-in customer lands on Pesanan; everyone else lands on the storefront,
 * where the catalog can be browsed before an account exists.
 */
export default function ClientStart() {
    const { navigate } = useRouter();
    const target = isSignedIn("client") ? signedInHome("client") : signedOutEntry("client");

    useEffect(() => {
        navigate(target, { replace: true });
    }, [navigate, target]);

    return <ScreenLoading label="Membuka Anteraja Frozen…" />;
}
