<?php

namespace Database\Seeders;

use App\Models\AccountingAccount;
use App\Models\Company;
use Illuminate\Database\Seeder;

class AccountingAccountSeeder extends Seeder
{
    private const DEFAULT_NOTES = '系統預設會計科目';

    private const ACCOUNTS = [
        ['code' => '1101', 'name' => '庫存現金', 'type' => 'asset'],
        ['code' => '1102', 'name' => '零用金', 'type' => 'asset'],
        ['code' => '1103', 'name' => '銀行存款', 'type' => 'asset'],
        ['code' => '1104', 'name' => '定期存款', 'type' => 'asset'],
        ['code' => '1105', 'name' => '外幣存款', 'type' => 'asset'],
        ['code' => '1111', 'name' => '短期投資', 'type' => 'asset'],
        ['code' => '1131', 'name' => '應收票據', 'type' => 'asset'],
        ['code' => '1132', 'name' => '應收帳款', 'type' => 'asset'],
        ['code' => '1133', 'name' => '應收帳款-關係人', 'type' => 'asset'],
        ['code' => '1134', 'name' => '其他應收款', 'type' => 'asset'],
        ['code' => '1141', 'name' => '備抵呆帳', 'type' => 'asset'],
        ['code' => '1151', 'name' => '進項稅額', 'type' => 'asset'],
        ['code' => '1152', 'name' => '暫付稅款', 'type' => 'asset'],
        ['code' => '1161', 'name' => '預付款項', 'type' => 'asset'],
        ['code' => '1162', 'name' => '預付薪資', 'type' => 'asset'],
        ['code' => '1163', 'name' => '預付租金', 'type' => 'asset'],
        ['code' => '1164', 'name' => '預付保險費', 'type' => 'asset'],
        ['code' => '1201', 'name' => '存貨', 'type' => 'asset'],
        ['code' => '1202', 'name' => '在製品', 'type' => 'asset'],
        ['code' => '1203', 'name' => '原物料', 'type' => 'asset'],
        ['code' => '1301', 'name' => '長期投資', 'type' => 'asset'],
        ['code' => '1401', 'name' => '土地', 'type' => 'asset'],
        ['code' => '1411', 'name' => '房屋及建築', 'type' => 'asset'],
        ['code' => '1421', 'name' => '機器設備', 'type' => 'asset'],
        ['code' => '1431', 'name' => '運輸設備', 'type' => 'asset'],
        ['code' => '1441', 'name' => '辦公設備', 'type' => 'asset'],
        ['code' => '1442', 'name' => '電腦及週邊設備', 'type' => 'asset'],
        ['code' => '1451', 'name' => '累計折舊-房屋及建築', 'type' => 'asset'],
        ['code' => '1452', 'name' => '累計折舊-機器設備', 'type' => 'asset'],
        ['code' => '1453', 'name' => '累計折舊-運輸設備', 'type' => 'asset'],
        ['code' => '1454', 'name' => '累計折舊-辦公設備', 'type' => 'asset'],
        ['code' => '1455', 'name' => '累計折舊-電腦設備', 'type' => 'asset'],
        ['code' => '1501', 'name' => '無形資產', 'type' => 'asset'],
        ['code' => '1502', 'name' => '商譽', 'type' => 'asset'],
        ['code' => '1503', 'name' => '電腦軟體', 'type' => 'asset'],
        ['code' => '1601', 'name' => '存出保證金', 'type' => 'asset'],
        ['code' => '2101', 'name' => '短期借款', 'type' => 'liability'],
        ['code' => '2102', 'name' => '應付票據', 'type' => 'liability'],
        ['code' => '2103', 'name' => '應付帳款', 'type' => 'liability'],
        ['code' => '2104', 'name' => '應付薪資', 'type' => 'liability'],
        ['code' => '2105', 'name' => '應付費用', 'type' => 'liability'],
        ['code' => '2106', 'name' => '應付佣金', 'type' => 'liability'],
        ['code' => '2111', 'name' => '銷項稅額', 'type' => 'liability'],
        ['code' => '2112', 'name' => '應付營業稅', 'type' => 'liability'],
        ['code' => '2113', 'name' => '代扣所得稅', 'type' => 'liability'],
        ['code' => '2114', 'name' => '代扣勞健保', 'type' => 'liability'],
        ['code' => '2121', 'name' => '預收款項', 'type' => 'liability'],
        ['code' => '2131', 'name' => '其他應付款', 'type' => 'liability'],
        ['code' => '2201', 'name' => '長期借款', 'type' => 'liability'],
        ['code' => '2202', 'name' => '應付公司債', 'type' => 'liability'],
        ['code' => '2301', 'name' => '存入保證金', 'type' => 'liability'],
        ['code' => '3101', 'name' => '資本', 'type' => 'equity'],
        ['code' => '3201', 'name' => '資本公積', 'type' => 'equity'],
        ['code' => '3301', 'name' => '法定盈餘公積', 'type' => 'equity'],
        ['code' => '3302', 'name' => '特別盈餘公積', 'type' => 'equity'],
        ['code' => '3401', 'name' => '累積盈虧', 'type' => 'equity'],
        ['code' => '3402', 'name' => '本期損益', 'type' => 'equity'],
        ['code' => '4101', 'name' => '銷貨收入', 'type' => 'revenue'],
        ['code' => '4102', 'name' => '銷貨退回', 'type' => 'revenue'],
        ['code' => '4103', 'name' => '銷貨折讓', 'type' => 'revenue'],
        ['code' => '4111', 'name' => '勞務收入', 'type' => 'revenue'],
        ['code' => '4112', 'name' => '服務收入', 'type' => 'revenue'],
        ['code' => '4113', 'name' => '佣金收入', 'type' => 'revenue'],
        ['code' => '4201', 'name' => '利息收入', 'type' => 'revenue'],
        ['code' => '4202', 'name' => '投資收入', 'type' => 'revenue'],
        ['code' => '4203', 'name' => '兌換利益', 'type' => 'revenue'],
        ['code' => '4204', 'name' => '處分資產利益', 'type' => 'revenue'],
        ['code' => '4205', 'name' => '其他收入', 'type' => 'revenue'],
        ['code' => '5101', 'name' => '銷貨成本', 'type' => 'cost'],
        ['code' => '5102', 'name' => '進貨', 'type' => 'cost'],
        ['code' => '5103', 'name' => '進貨運費', 'type' => 'cost'],
        ['code' => '5104', 'name' => '進貨退出', 'type' => 'cost'],
        ['code' => '5105', 'name' => '進貨折讓', 'type' => 'cost'],
        ['code' => '5201', 'name' => '勞務成本', 'type' => 'cost'],
        ['code' => '5202', 'name' => '服務成本', 'type' => 'cost'],
        ['code' => '5301', 'name' => '製造費用', 'type' => 'cost'],
        ['code' => '6101', 'name' => '薪資費用', 'type' => 'expense'],
        ['code' => '6102', 'name' => '加班費', 'type' => 'expense'],
        ['code' => '6103', 'name' => '獎金', 'type' => 'expense'],
        ['code' => '6104', 'name' => '勞健保費', 'type' => 'expense'],
        ['code' => '6105', 'name' => '退休金', 'type' => 'expense'],
        ['code' => '6106', 'name' => '員工福利', 'type' => 'expense'],
        ['code' => '6111', 'name' => '租金支出', 'type' => 'expense'],
        ['code' => '6112', 'name' => '文具用品', 'type' => 'expense'],
        ['code' => '6113', 'name' => '旅費', 'type' => 'expense'],
        ['code' => '6114', 'name' => '運費', 'type' => 'expense'],
        ['code' => '6115', 'name' => '郵電費', 'type' => 'expense'],
        ['code' => '6116', 'name' => '修繕費', 'type' => 'expense'],
        ['code' => '6117', 'name' => '廣告費', 'type' => 'expense'],
        ['code' => '6118', 'name' => '水電瓦斯費', 'type' => 'expense'],
        ['code' => '6119', 'name' => '保險費', 'type' => 'expense'],
        ['code' => '6120', 'name' => '佣金支出', 'type' => 'expense'],
        ['code' => '6121', 'name' => '交際費', 'type' => 'expense'],
        ['code' => '6122', 'name' => '捐贈', 'type' => 'expense'],
        ['code' => '6123', 'name' => '稅捐', 'type' => 'expense'],
        ['code' => '6124', 'name' => '折舊費', 'type' => 'expense'],
        ['code' => '6125', 'name' => '攤銷費', 'type' => 'expense'],
        ['code' => '6126', 'name' => '訓練費', 'type' => 'expense'],
        ['code' => '6127', 'name' => '雜項購置', 'type' => 'expense'],
        ['code' => '6128', 'name' => '書報雜誌', 'type' => 'expense'],
        ['code' => '6129', 'name' => '勞務費', 'type' => 'expense'],
        ['code' => '6130', 'name' => '其他費用', 'type' => 'expense'],
        ['code' => '6201', 'name' => '利息費用', 'type' => 'expense'],
        ['code' => '6202', 'name' => '兌換損失', 'type' => 'expense'],
        ['code' => '6203', 'name' => '處分資產損失', 'type' => 'expense'],
        ['code' => '6204', 'name' => '投資損失', 'type' => 'expense'],
        ['code' => '6301', 'name' => '所得稅費用', 'type' => 'expense'],
    ];

    public function run(): void
    {
        Company::query()
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(50, function ($companies): void {
                foreach ($companies as $company) {
                    foreach (self::ACCOUNTS as $account) {
                        AccountingAccount::query()->firstOrCreate(
                            [
                                'company_id' => $company->id,
                                'code' => $account['code'],
                            ],
                            [
                                'branch_id' => null,
                                'name' => $account['name'],
                                'type' => $account['type'],
                                'opening_balance' => 0,
                                'is_active' => true,
                                'notes' => self::DEFAULT_NOTES,
                                'created_by' => null,
                                'updated_by' => null,
                            ]
                        );
                    }
                }
            });
    }
}
