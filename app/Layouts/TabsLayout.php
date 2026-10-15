<?php

namespace App\Layouts;

use App\Icons\Android;
use App\Icons\Ios;
use Native\Mobile\Edge\Layouts\Builders\NavBar;
use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;

class TabsLayout extends NativeLayout
{
    public function navBar(NativeComponent $screen): ?NavBar
    {
        return NavBar::make()->title($screen->navTitle());
    }

    public function tabBar(NativeComponent $screen): ?TabBar
    {
        return TabBar::make()
            ->add(Tab::link('Speed', '/', ios: Ios::GaugeWithNeedle, android: Android::Speed))
            ->add(Tab::link('Trips', '/trips', ios: Ios::ListBullet, android: Android::History))
            ->add(Tab::link('Stats', '/stats', ios: Ios::ChartBarFill, android: Android::BarChart))
            ->add(Tab::link('Fuel', '/fuel', ios: Ios::FuelpumpFill, android: Android::LocalGasStation))
            ->add(Tab::link('Settings', '/settings', ios: Ios::Gearshape, android: Android::Settings));
    }
}
