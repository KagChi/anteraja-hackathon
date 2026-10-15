import { useEffect } from "react";
import { ScreenLoading } from "../../components/StateCard";
import { useRouter } from "../../lib/router";
import { isSignedIn, signedInHome, signedOutEntry } from "../../lib/token";

/**
 * The gate the courier app opens on.
 *
 * A signed-in courier lands on the task board; everyone else lands on login.
 */
export default function CourierStart() {
    const { navigate } = useRouter();
    const target = isSignedIn("courier") ? signedInHome("courier") : signedOutEntry("courier");

    useEffect(() => {
        navigate(target, { replace: true });
    }, [navigate, target]);

    return <ScreenLoading label="Membuka Anteraja Kurir…" />;
}
