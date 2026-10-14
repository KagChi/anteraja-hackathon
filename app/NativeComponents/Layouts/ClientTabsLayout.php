<?php

declare(strict_types=1);

namespace App\NativeComponents\Layouts;

use App\Icons\Android;
use App\Icons\Ios;
use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;

/**
 * Bottom tabs shared by the three customer tab screens — Pesanan, Riwayat,
 * Profil.
 *
 * The bar lives in one place because all three screens carry identical
 * chrome; the pushed flow screens (tracking, schedule, issue) sit outside
 * this group so they keep their own back-navigable top bar instead of the
 * tabs.
 */
final class ClientTabsLayout extends NativeLayout {
    public function tabBar(NativeComponent $screen): ?TabBar {
        return TabBar::make()
            ->activeColor(theme('primary'))
            ->backgroundColor(theme('surface'))
            ->textColor(theme('secondary'))
            ->add(Tab::link('Pesanan', '/client/orders', ios: Ios::Receipt, android: Android::ReceiptLong))
            ->add(Tab::link('Riwayat', '/client/history', ios: Ios::ClockArrowCirclepath, android: Android::History))
            ->add(Tab::link('Profil', '/client/profile', ios: Ios::PersonTextRectangle, android: Android::AccountCircle));
    }
}
