using System;
using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace QRCodeApp.API.Migrations
{
    /// <inheritdoc />
    public partial class AddCallMeNowQrStickers : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropIndex(
                name: "IX_Persons_Email",
                table: "Persons");

            migrationBuilder.DropIndex(
                name: "IX_Persons_PhoneNumber",
                table: "Persons");

            migrationBuilder.CreateTable(
                name: "QrStickers",
                columns: table => new
                {
                    Id = table.Column<int>(type: "int", nullable: false)
                        .Annotation("SqlServer:Identity", "1, 1"),
                    PublicId = table.Column<string>(type: "nvarchar(40)", maxLength: 40, nullable: false),
                    ProductType = table.Column<string>(type: "nvarchar(50)", maxLength: 50, nullable: false),
                    Status = table.Column<byte>(type: "tinyint", nullable: false),
                    PersonId = table.Column<int>(type: "int", nullable: true),
                    ScanCount = table.Column<int>(type: "int", nullable: false),
                    CreatedAt = table.Column<DateTime>(type: "datetime2", nullable: false),
                    ActivatedAt = table.Column<DateTime>(type: "datetime2", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_QrStickers", x => x.Id);
                    table.ForeignKey(
                        name: "FK_QrStickers_Persons_PersonId",
                        column: x => x.PersonId,
                        principalTable: "Persons",
                        principalColumn: "Id",
                        onDelete: ReferentialAction.SetNull);
                });

            migrationBuilder.CreateIndex(
                name: "IX_QrStickers_PersonId",
                table: "QrStickers",
                column: "PersonId");

            migrationBuilder.CreateIndex(
                name: "IX_QrStickers_PublicId",
                table: "QrStickers",
                column: "PublicId",
                unique: true);
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "QrStickers");

            migrationBuilder.CreateIndex(
                name: "IX_Persons_Email",
                table: "Persons",
                column: "Email",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_Persons_PhoneNumber",
                table: "Persons",
                column: "PhoneNumber",
                unique: true);
        }
    }
}
