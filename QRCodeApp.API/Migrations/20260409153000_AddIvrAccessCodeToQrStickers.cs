using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace QRCodeApp.API.Migrations
{
    /// <inheritdoc />
    public partial class AddIvrAccessCodeToQrStickers : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.AddColumn<string>(
                name: "IvrAccessCode",
                table: "QrStickers",
                type: "nvarchar(6)",
                maxLength: 6,
                nullable: true);

            migrationBuilder.CreateIndex(
                name: "IX_QrStickers_IvrAccessCode",
                table: "QrStickers",
                column: "IvrAccessCode",
                unique: true,
                filter: "[IvrAccessCode] IS NOT NULL");
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropIndex(
                name: "IX_QrStickers_IvrAccessCode",
                table: "QrStickers");

            migrationBuilder.DropColumn(
                name: "IvrAccessCode",
                table: "QrStickers");
        }
    }
}
