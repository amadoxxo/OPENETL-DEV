import { Routes } from '@angular/router';
import { AuthGuard } from './auth.guard';

export const appRoutes: Routes = [
    // Si se escribe una ruta en la URL, que no coincide con ninguna de login y recuperar, nos lleva al login
    {
        path: '',
        redirectTo: 'auth/login',
        pathMatch: 'full'
    },
    {
        path: 'auth',
        loadChildren: () => import('./main/autenticacion/autenticacion.module').then( l => l.AutenticacionModule ) 
    },
    {
        path: '',
        children: [
            {
                canActivate: [AuthGuard],
                canActivateChild: [AuthGuard],
                path: 'dashboard',
                loadChildren: () => import('./main/dashboard/dashboard.module').then( d => d.DashboardModule ) 
            },
            {
                canActivate: [AuthGuard],
                canActivateChild: [AuthGuard],
                path: 'perfil_usuario',
                loadChildren: () => import('./main/perfil_usuario/perfil_usuario.module').then( p => p.PerfilUsuarioModule )
            },
            {
                canActivate: [AuthGuard],
                canActivateChild: [AuthGuard],
                path: 'sistema',
                loadChildren: () => import('./main/sistema/sistema.module').then( s => s.SistemaModule ), 
            },
            {
                canActivate: [AuthGuard],
                canActivateChild: [AuthGuard],
                path: 'parametros',
                loadChildren: () => import('./main/parametros/parametros.module').then( c => c.ParametrosModule ) 
            },
            {
                canActivate: [AuthGuard],
                canActivateChild: [AuthGuard],
                path: 'recepcion',
                loadChildren: () => import('./main/recepcion/recepcion.module').then( rec => rec.RecepcionModule ) 
            },
            {
                canActivate: [AuthGuard],
                canActivateChild: [AuthGuard],
                path: 'nomina-electronica',
                loadChildren: () => import('./main/nomina-electronica/nomina-electronica.module').then( nom => nom.NominaElectronicaModule ) 
            },
            {
                canActivate: [AuthGuard],
                canActivateChild: [AuthGuard],
                path: 'configuracion',
                loadChildren: () => import('./main/configuracion/configuracion.module').then( c => c.ConfiguracionModule ) 
            },
            {
				canActivate: [AuthGuard],
				canActivateChild: [AuthGuard],
				path: 'facturacion-web',
				loadChildren: () => import('./main/facturacion-web/facturacion-web/facturacion-web.module').then(fac => fac.FacturacionWebModule)
			},
			{
				canActivate: [AuthGuard],
				canActivateChild: [AuthGuard],
				path: 'emision',
				loadChildren: () => import('./main/emision/emision.module').then(emi => emi.EmisionModule)
			},
			{
				canActivate: [AuthGuard],
				canActivateChild: [AuthGuard],
				path: 'documento-soporte',
				loadChildren: () => import('./main/documento-soporte/documento-soporte.module').then(doc => doc.DocumentoSoporteModule)
			},
			{
				canActivate: [AuthGuard],
				canActivateChild: [AuthGuard],
				path: 'emision/documentos-cco',
				loadChildren: () => import('./main/proyectos-especiales/emision/dhl-express/documentos-cco/documentos-cco.module').then(cco => cco.DocumentosCCOModule)
			},
            {
				canActivate: [AuthGuard],
				canActivateChild: [AuthGuard],
				path: 'radian',
				loadChildren: () => import('./main/radian/radian.module').then(rad => rad.RadianModule)
			}, 
        ]
    }
];
