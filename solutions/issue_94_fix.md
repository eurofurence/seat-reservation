Below is a **minimal, production‑ready skeleton** that shows how you can add a Patreon integration to your seat‑reservation system.  
The code is written for an **ASP.NET Core 8** web app that already uses **Identity** for authentication.  
Feel free to drop the snippets into the appropriate folders and adjust namespaces / DI registrations as needed.

> **⚠️ Note** – The feature request is *not* approved by the Board of Directors.  
> Use this code only in a sandbox or after you receive formal approval.

---

## 1.  Database – Store the Patreon membership

```csharp
// 2024-08-27: Migration – CreateUserPatreonMembershipsTable.cs
using Microsoft.EntityFrameworkCore.Migrations;

public partial class CreateUserPatreonMembershipsTable : Migration
{
    protected override void Up(MigrationBuilder migrationBuilder)
    {
        migrationBuilder.CreateTable(
            name: "UserPatreonMemberships",
            columns: table => new
            {
                Id = table.Column<Guid>(nullable: false),
                UserId = table.Column<string>(nullable: false),
                PatreonUserId = table.Column<string>(nullable: false),
                Tier = table.Column<string>(nullable: false),
                ExpirationDate = table.Column<DateTime>(nullable: false)
            },
            constraints: table =>
            {
                table.PrimaryKey("PK_UserPatreonMemberships", x => x.Id);
                table.ForeignKey(
                    name: "FK_UserPatreonMemberships_AspNetUsers_UserId",
                    column: x => x.UserId,
                    principalTable: "AspNetUsers",
                    principalColumn: "Id",
                    onDelete: ReferentialAction.Cascade);
            });

        migrationBuilder.CreateIndex(
            name: "IX_UserPatreonMemberships_UserId",
            table: "UserPatreonMemberships",
            column: "UserId");
    }

    protected override void Down(MigrationBuilder migrationBuilder)
    {
        migrationBuilder.DropTable(name: "UserPatreonMemberships");
    }
}
```

```csharp
// 2024-08-27: Entity – UserPatreonMembership.cs
using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

public class UserPatreonMembership
{
    [Key]
    public Guid Id { get; set; }

    [Required]
    public string UserId { get; set; }          // FK to AspNetUsers

    [Required]
    public string PatreonUserId { get; set; }   // Patreon’s user id

    [Required]
    public string Tier { get; set; }            // e.g. “PPS Premium”

    [Required]
    public DateTime ExpirationDate { get; set; }

    // Navigation
    [ForeignKey(nameof(UserId))]
    public ApplicationUser User { get; set; }
}
```

> **Tip** – Add a `DbSet<UserPatreonMembership>` to your `ApplicationDbContext`.

---

##