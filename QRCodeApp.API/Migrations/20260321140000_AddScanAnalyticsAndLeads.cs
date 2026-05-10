using System;
using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace QRCodeApp.API.Migrations
{
    /// <inheritdoc />
    public class AddScanAnalyticsAndLeads : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.AddColumn<int>(
                name: "UniqueScannerCount",
                table: "QrStickers",
                type: "int",
                nullable: false,
                defaultValue: 0);

            migrationBuilder.CreateTable(
                name: "MarketingLeads",
                columns: table => new
                {
                    Id = table.Column<int>(type: "int", nullable: false)
                        .Annotation("SqlServer:Identity", "1, 1"),
                    PhoneNormalized = table.Column<string>(type: "nvarchar(20)", maxLength: 20, nullable: false),
                    QrPublicId = table.Column<string>(type: "nvarchar(40)", maxLength: 40, nullable: true),
                    ReferralCode = table.Column<string>(type: "nvarchar(32)", maxLength: 32, nullable: true),
                    Source = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: false),
                    CreatedAtUtc = table.Column<DateTime>(type: "datetime2", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_MarketingLeads", x => x.Id);
                });

            migrationBuilder.CreateTable(
                name: "QrScanEvents",
                columns: table => new
                {
                    Id = table.Column<long>(type: "bigint", nullable: false)
                        .Annotation("SqlServer:Identity", "1, 1"),
                    QrStickerId = table.Column<int>(type: "int", nullable: false),
                    VisitorHash = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: false),
                    ScannedAtUtc = table.Column<DateTime>(type: "datetime2", nullable: false),
                    UserAgentSnippet = table.Column<string>(type: "nvarchar(256)", maxLength: 256, nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_QrScanEvents", x => x.Id);
                    table.ForeignKey(
                        name: "FK_QrScanEvents_QrStickers_QrStickerId",
                        column: x => x.QrStickerId,
                        principalTable: "QrStickers",
                        principalColumn: "Id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateIndex(
                name: "IX_MarketingLeads_CreatedAtUtc",
                table: "MarketingLeads",
                column: "CreatedAtUtc");

            migrationBuilder.CreateIndex(
                name: "IX_MarketingLeads_PhoneNormalized",
                table: "MarketingLeads",
                column: "PhoneNormalized");

            migrationBuilder.CreateIndex(
                name: "IX_QrScanEvents_QrStickerId_VisitorHash",
                table: "QrScanEvents",
                columns: new[] { "QrStickerId", "VisitorHash" });

            migrationBuilder.CreateIndex(
                name: "IX_QrScanEvents_ScannedAtUtc",
                table: "QrScanEvents",
                column: "ScannedAtUtc");
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "MarketingLeads");

            migrationBuilder.DropTable(
                name: "QrScanEvents");

            migrationBuilder.DropColumn(
                name: "UniqueScannerCount",
                table: "QrStickers");
        }
    }
}
