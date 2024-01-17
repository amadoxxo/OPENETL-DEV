export class TabFacturacionWeb {
    public id: number;
    public itemId: number;
    public title: string;
    public tabData: any;
    public active: boolean;

    constructor(
        itemId: number,
        title: string, 
        tabData: any
    ) {
        this.itemId = itemId;
        this.title = title;
        this.tabData = tabData;
    }
}
