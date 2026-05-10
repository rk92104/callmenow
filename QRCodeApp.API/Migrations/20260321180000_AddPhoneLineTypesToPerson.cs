using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace QRCodeApp.API.Migrations
{
    /// <inheritdoc />
    public class AddPhoneLineTypesToPerson : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.AddColumn<string>(
                name: "EmergencyContactPhoneType",
                table: "Persons",
                type: "nvarchar(16)",
                maxLength: 16,
                nullable: false,
                defaultValue: "Mobile");

            migrationBuilder.AddColumn<string>(
                name: "PhoneNumberType",
                table: "Persons",
                type: "nvarchar(16)",
                maxLength: 16,
                nullable: false,
                defaultValue: "Mobile");
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropColumn(
                name: "EmergencyContactPhoneType",
                table: "Persons");

            migrationBuilder.DropColumn(
                name: "PhoneNumberType",
                table: "Persons");
        }
    }
}
