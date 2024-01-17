import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { HttpClientModule, HTTP_INTERCEPTORS } from '@angular/common/http';
import { BrowserAnimationsModule } from '@angular/platform-browser/animations';
import { RouterModule } from '@angular/router';
import { MatMomentDateModule } from '@angular/material-moment-adapter';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatSnackBarModule } from '@angular/material/snack-bar';
import { TranslateModule } from '@ngx-translate/core';
import 'hammerjs';

import { FuseModule } from '@fuse/fuse.module';
import { FuseSharedModule } from '@fuse/shared.module';
import { FuseProgressBarModule, FuseSidebarModule, FuseThemeOptionsModule } from '@fuse/components';
import { fuseConfig } from 'app/fuse-config';
import { AppComponent } from 'app/app.component';
import { LayoutModule } from 'app/layout/layout.module';
import {STEPPER_GLOBAL_OPTIONS} from '@angular/cdk/stepper';

// openETL
import { Auth } from './services/auth/auth.service';
import { environment } from 'environments/environment';
import { JwtModule, JWT_OPTIONS } from '@auth0/angular-jwt';
import { LoaderModule } from './shared/loader/loader.module';
import { JwtInterceptor } from './services/interceptor/jwtInterceptor.service';
import { DownloadFileErrorInterceptor } from './services/interceptor/downloadFileErrorInterceptor.service';

import { appRoutes } from './app.routing';

export function jwtOptionsFactory() {
    return {
        tokenGetter: () => {
            return localStorage.getItem('id_token');
        },
        whitelistedDomains: [
            environment.white_domains.local, 
            environment.white_domains.server_api_main, 
            environment.white_domains.server_api_di]
    };
}

@NgModule({
    declarations: [AppComponent],
    providers: [
        { provide: HTTP_INTERCEPTORS, useClass: JwtInterceptor, multi: true },
        { provide: HTTP_INTERCEPTORS, useClass: DownloadFileErrorInterceptor, multi: true },
        { provide: STEPPER_GLOBAL_OPTIONS, useValue: { showError: true } },
        Auth,
    ],
    imports: [
        BrowserModule,
        BrowserAnimationsModule,
        HttpClientModule,
        JwtModule.forRoot({
            jwtOptionsProvider: {
                provide: JWT_OPTIONS,
                useFactory: jwtOptionsFactory
            }
        }),
        RouterModule.forRoot(appRoutes,
        {
            useHash: true,
            enableTracing: false,
            relativeLinkResolution: 'legacy'
        }),
        TranslateModule.forRoot(),

        // Material moment date module
        MatMomentDateModule,

        // Material
        MatButtonModule,
        MatIconModule,
        MatSnackBarModule,

        // Fuse modules
        FuseModule.forRoot(fuseConfig),
        FuseProgressBarModule,
        FuseSharedModule,
        FuseSidebarModule,
        FuseThemeOptionsModule,

        // openETL modules
        LoaderModule,
        LayoutModule
    ],
    bootstrap   : [
        AppComponent
    ]
})

export class AppModule{}
