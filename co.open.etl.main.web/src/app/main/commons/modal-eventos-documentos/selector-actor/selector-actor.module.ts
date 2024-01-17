import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {SelectorActorComponent} from './selector-actor.component';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {NgSelectModule} from '@ng-select/ng-select';


@NgModule({
    declarations: [SelectorActorComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule,
        NgSelectModule
    ],
    exports: [SelectorActorComponent],
    schemas: [ CUSTOM_ELEMENTS_SCHEMA]
})
export class SelectorActorModule {} 
