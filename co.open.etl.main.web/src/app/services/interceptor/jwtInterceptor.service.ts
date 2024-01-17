import { Injectable } from '@angular/core';
import { HttpInterceptor, HttpHandler, HttpRequest, HttpEvent, HttpResponse, HttpErrorResponse } from '@angular/common/http';
import { Auth } from 'app/services/auth/auth.service';
import { JwtHelperService } from '@auth0/angular-jwt';
import { Router } from '@angular/router';

import { Observable } from 'rxjs';
import { map, catchError } from 'rxjs/operators';
import { throwError } from 'rxjs';

@Injectable()
export class JwtInterceptor implements HttpInterceptor {
    constructor(
        private authService: Auth,
        private router: Router,
        private jwtHelperService: JwtHelperService,
    ) {}

    intercept(
        request: HttpRequest<any>,
        next: HttpHandler
    ): Observable<HttpEvent<any>> {
        // Clone the request object
        let newRequest = request.clone();

        // Request
        if ( this.authService.access_token && !this.jwtHelperService.isTokenExpired(this.authService.access_token)) {
            newRequest = request.clone({
                headers: request.headers.set('Authorization', 'Bearer ' + this.authService.access_token)
            });
        }

        return next.handle(newRequest).pipe(
            map((event: HttpEvent<HttpResponse<any>>) => {
                if (event instanceof HttpResponse) {
                    if (event.headers.get('Authorization')) {
                        const TOKEN = event.headers.get('Authorization').split(' ');
                        localStorage.setItem('id_token', TOKEN[1]);
                    }

                    if(event.headers.get('X-Cartera-Vencida-Mensaje')) {
                        localStorage.setItem('cartera_vencida_mensaje', event.headers.get('X-Cartera-Vencida-Mensaje'));
                    } else {
                        if(localStorage.getItem('cartera_vencida_mensaje') !== null && localStorage.getItem('cartera_vencida_mensaje') !== undefined)
                            localStorage.removeItem('cartera_vencida_mensaje');
                    }
                }
                return event;
            }),
            catchError(error => {
                if (error.headers && error.headers.get('Authorization')) {
                    const TOKEN = error.headers.get('Authorization').split(' ');
                    localStorage.setItem('id_token', TOKEN[1]);
                }
                if(error.headers && error.headers.get('X-Cartera-Vencida-Mensaje')) {
                    localStorage.setItem('cartera_vencida_mensaje', error.headers.get('X-Cartera-Vencida-Mensaje'));
                } else {
                    if(localStorage.getItem('cartera_vencida_mensaje') !== null && localStorage.getItem('cartera_vencida_mensaje') !== undefined)
                        localStorage.removeItem('cartera_vencida_mensaje');
                }
                if (error instanceof HttpErrorResponse) {
                    switch (error.status) {
                        case 401:
                            localStorage.removeItem('cartera_vencida_mensaje');
                            localStorage.removeItem('acl');
                            localStorage.removeItem('id_token');
                            this.router.navigate(['/auth/login']);
                            return throwError(error.error);
                        default:
                            return throwError(error.error);
                    }
                }
            })
        );
    }
}
